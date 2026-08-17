<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\TranslatableMessage;

#[IsGranted('ROLE_ADMIN_RESSOURCE')]
#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);
        return $this->redirect($adminUrlGenerator->setController(ResourceCrudController::class)->generateUrl());
    }

    public function configureAssets(): Assets
    {
        return Assets::new()->addHtmlContentToHead('<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Librebooking')
            ->setFaviconPath('data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 128 128%22><rect width=%22128%22 height=%22128%22 fill=%22%234f46e5%22/></svg>')
            ->setTranslationDomain('messages')
            ->renderContentMaximized();
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToRoute(new TranslatableMessage('admin.back_to_site'), 'fa-solid fa-arrow-left', 'app_home');

        yield MenuItem::section(new TranslatableMessage('admin.section.reservations'));
        yield MenuItem::linkTo(ReservationSeriesCrudController::class, new TranslatableMessage('admin.reservation_series.plural'), 'fa-solid fa-calendar-check')->setAction('index');

        yield MenuItem::section(new TranslatableMessage('admin.section.resources'));
        yield MenuItem::linkTo(ResourceCrudController::class, new TranslatableMessage('admin.resource.plural'), 'fa-solid fa-door-open')->setAction('index');
        yield MenuItem::linkTo(ResourceCategoryCrudController::class, new TranslatableMessage('admin.resource_category.plural'), 'fa-solid fa-tags')->setAction('index');
        yield MenuItem::linkTo(ResourceGroupCrudController::class, new TranslatableMessage('admin.resource_group.plural'), 'fa-solid fa-users-gear')->setAction('index');

        yield MenuItem::section('Prestations & Services');
        yield MenuItem::linkTo(PrestaCategorieCrudController::class, 'Catégories', 'fa-solid fa-tags')->setAction('index');
        yield MenuItem::linkTo(PrestataireCrudController::class, 'Prestataires', 'fa-solid fa-user-tie')->setAction('index');
        yield MenuItem::linkTo(ServiceCrudController::class, 'Services / Prestations', 'fa-solid fa-concierge-bell')->setAction('index');
        yield MenuItem::linkTo(PrestaAbsenceCrudController::class, 'Absences', 'fa-solid fa-plane-departure')->setAction('index');
        yield MenuItem::linkTo(SessionCrudController::class, 'Sessions (RDVs)', 'fa-solid fa-calendar-days')->setAction('index');

        yield MenuItem::section(new TranslatableMessage('admin.section.configuration'));
        yield MenuItem::linkTo(ScheduleCrudController::class, new TranslatableMessage('admin.schedule.plural'), 'fa-solid fa-clock')->setAction('index');
        yield MenuItem::linkTo(LayoutCrudController::class, new TranslatableMessage('admin.layout.plural'), 'fa-solid fa-table-cells')->setAction('index');
        yield MenuItem::linkTo(AnnouncementCrudController::class, new TranslatableMessage('admin.announcement.plural'), 'fa-solid fa-bullhorn')->setAction('index');

        yield MenuItem::section(new TranslatableMessage('admin.section.system'))->setPermission('ROLE_SUPER_ADMIN');
        yield MenuItem::linkTo(UserCrudController::class, new TranslatableMessage('admin.user.plural'), 'fa-solid fa-users')
            ->setAction('index')
            ->setPermission('ROLE_SUPER_ADMIN');
        yield MenuItem::linkToRoute('Configuration', 'fa-solid fa-sliders', 'admin_configuration')
            ->setPermission('ROLE_SUPER_ADMIN');
    }
}
