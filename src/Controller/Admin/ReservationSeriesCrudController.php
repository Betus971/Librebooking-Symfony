<?php

namespace App\Controller\Admin;

use App\Domain\Reservation\ReservationWorkflow;
use App\Entity\ReservationSeries;
use App\Entity\ReservationStatus;
use App\Entity\User;
use App\Notification\ReservationNotifier;
use App\Security\Voter\ReservationSeriesVoter;
use App\Service\WaitlistService;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\TranslatableMessage;

class ReservationSeriesCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ReservationSeries::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular(new TranslatableMessage('admin.reservation_series.singular'))
            ->setEntityLabelInPlural(new TranslatableMessage('admin.reservation_series.plural'))
            ->setDefaultSort(['dateCreated' => 'DESC'])
            ->setSearchFields(['title', 'description', 'owner.email', 'owner.lname'])
            ->setPaginatorPageSize(30);
    }

    public function configureActions(Actions $actions): Actions
    {
        $approve = Action::new('approve', new TranslatableMessage('admin.reservation.action.approve'), 'fa-solid fa-check')
            ->linkToCrudAction('approve')
            ->displayIf(static fn (ReservationSeries $s): bool => $s->getStatus()?->getId() === ReservationStatus::PENDING);

        $reject = Action::new('reject', new TranslatableMessage('admin.reservation.action.reject'), 'fa-solid fa-xmark')
            ->linkToCrudAction('reject')
            ->displayIf(static fn (ReservationSeries $s): bool => $s->getStatus()?->getId() === ReservationStatus::PENDING);

        $cancel = Action::new('cancelReservation', new TranslatableMessage('admin.reservation.action.cancel'), 'fa-solid fa-ban')
            ->linkToCrudAction('cancelReservation')
            ->displayIf(static fn (ReservationSeries $s): bool => in_array(
                $s->getStatus()?->getId(),
                [ReservationStatus::PENDING, ReservationStatus::APPROVED],
                true
            ));

        return $actions
            ->disable(Action::NEW, Action::DELETE, Action::EDIT)
            ->add(Crud::PAGE_INDEX, $approve)
            ->add(Crud::PAGE_INDEX, $reject)
            ->add(Crud::PAGE_INDEX, $cancel)
            ->add(Crud::PAGE_DETAIL, $approve)
            ->add(Crud::PAGE_DETAIL, $reject)
            ->add(Crud::PAGE_DETAIL, $cancel);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('status', new TranslatableMessage('reservation.field.status')))
            ->add(EntityFilter::new('owner', new TranslatableMessage('reservation.field.owner')));
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('title', new TranslatableMessage('reservation.field.title'));
        yield AssociationField::new('owner', new TranslatableMessage('reservation.field.owner'));
        yield AssociationField::new('status', new TranslatableMessage('reservation.field.status'));

        if ($pageName === Crud::PAGE_INDEX) {
            yield DateTimeField::new('dateCreated', new TranslatableMessage('reservation.field.created_at'))
                ->setFormat('dd/MM/yyyy HH:mm');
            return;
        }

        yield FormField::addTab(new TranslatableMessage('admin.tab.general'))->setIcon('fa-solid fa-circle-info');

        yield TextareaField::new('description', new TranslatableMessage('reservation.field.description'))
            ->setRequired(false)
            ->setDisabled();

        yield AssociationField::new('type', new TranslatableMessage('reservation.field.type'))
            ->setDisabled();

        yield DateTimeField::new('dateCreated', new TranslatableMessage('reservation.field.created_at'))
            ->setFormat('dd/MM/yyyy HH:mm')
            ->setDisabled();

        yield FormField::addTab(new TranslatableMessage('reservation.instances'))->setIcon('fa-solid fa-calendar');

        yield AssociationField::new('instances', new TranslatableMessage('reservation.instances'))
            ->setDisabled();

        yield FormField::addTab(new TranslatableMessage('reservation.resources'))->setIcon('fa-solid fa-door-open');

        yield AssociationField::new('reservationResources', new TranslatableMessage('reservation.resources'))
            ->setDisabled();
    }

    #[AdminRoute]
    public function approve(
        AdminContext $context,
        Request $request,
        ReservationWorkflow $workflow,
        ReservationNotifier $notifier,
        AdminUrlGenerator $urlGenerator,
        WaitlistService $waitlist,
    ): Response {
        return $this->handleTransition('approve', $context, $request, $workflow, $notifier, $urlGenerator, $waitlist);
    }

    #[AdminRoute]
    public function reject(
        AdminContext $context,
        Request $request,
        ReservationWorkflow $workflow,
        ReservationNotifier $notifier,
        AdminUrlGenerator $urlGenerator,
        WaitlistService $waitlist,
    ): Response {
        return $this->handleTransition('reject', $context, $request, $workflow, $notifier, $urlGenerator, $waitlist);
    }

    #[AdminRoute]
    public function cancelReservation(
        AdminContext $context,
        Request $request,
        ReservationWorkflow $workflow,
        ReservationNotifier $notifier,
        AdminUrlGenerator $urlGenerator,
        WaitlistService $waitlist,
    ): Response {
        return $this->handleTransition('cancel', $context, $request, $workflow, $notifier, $urlGenerator, $waitlist);
    }

    /**
     * Logique commune aux trois transitions admin (approve / reject / cancel).
     *
     * - Vérifie le périmètre via {@see ReservationSeriesVoter::MANAGE}.
     * - GET  → affiche une page de confirmation (champ motif obligatoire pour reject).
     * - POST → vérifie le CSRF, applique la transition, notifie le demandeur,
     *          puis revient à la liste.
     */
    private function handleTransition(
        string $action,
        AdminContext $context,
        Request $request,
        ReservationWorkflow $workflow,
        ReservationNotifier $notifier,
        AdminUrlGenerator $urlGenerator,
        WaitlistService $waitlist,
    ): Response {
        /** @var ReservationSeries $series */
        $series = $context->getEntity()->getInstance();

        $this->denyAccessUnlessGranted(ReservationSeriesVoter::MANAGE, $series);

        $indexUrl = $urlGenerator->setController(self::class)->setAction(Crud::PAGE_INDEX)->generateUrl();
        $actionUrl = $urlGenerator
            ->setController(self::class)
            ->setAction($action)
            ->setEntityId($series->getId())
            ->generateUrl();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('reservation-'.$action.'-'.$series->getId(), (string) $request->request->get('_token'))) {
                $this->addFlash('danger', new TranslatableMessage('admin.reservation.flash.invalid_token'));

                return new RedirectResponse($indexUrl);
            }

            $reason = trim((string) $request->request->get('reason', ''));

            if ($action === 'reject' && $reason === '') {
                $this->addFlash('warning', new TranslatableMessage('admin.reservation.flash.reason_required'));

                return $this->renderConfirm($action, $series, $actionUrl, $indexUrl);
            }

            try {
                $workflow->ensureAllowed($action, $series);

                /** @var User|null $actor */
                $actor = $this->getUser();
                $workflow->apply($action, $series, $actor, $reason !== '' ? $reason : null);

                match ($action) {
                    'approve' => $notifier->approved($series),
                    'reject'  => $notifier->rejected($series, $reason),
                    'cancel'  => $notifier->cancelled($series),
                    default   => null,
                };

                // Liste d'attente : à l'annulation, prévenir les personnes en attente.
                if ('cancel' === $action) {
                    $waitlist->notifyForFreedSeries($series);
                }

                $this->addFlash('success', new TranslatableMessage('admin.reservation.flash.'.$action.'_done'));
            } catch (\DomainException|\LogicException $e) {
                $this->addFlash('warning', $e->getMessage());
            }

            return new RedirectResponse($indexUrl);
        }

        return $this->renderConfirm($action, $series, $actionUrl, $indexUrl);
    }

    private function renderConfirm(string $action, ReservationSeries $series, string $actionUrl, string $indexUrl): Response
    {
        return $this->render('admin/reservation/transition.html.twig', [
            'action'    => $action,
            'series'    => $series,
            'actionUrl' => $actionUrl,
            'indexUrl'  => $indexUrl,
            'csrfId'    => 'reservation-'.$action.'-'.$series->getId(),
        ]);
    }
}
