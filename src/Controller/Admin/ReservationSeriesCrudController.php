<?php

namespace App\Controller\Admin;

use App\Entity\ReservationSeries;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
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
        return $actions
            ->disable(Action::NEW, Action::DELETE, Action::EDIT);
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
}
