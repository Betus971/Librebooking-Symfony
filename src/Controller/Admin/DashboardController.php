<?php

namespace App\Controller\Admin;

use App\Entity\Announcement;
use App\Entity\ReservationSeries;
use App\Entity\Resource;
use App\Entity\ResourceCategory;
use App\Entity\ResourceGroup;
use App\Entity\Schedule;
use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
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
        yield MenuItem::linkToRoute(new TranslatableMessage('admin.back_to_site'), 'fa fa-arrow-left', 'app_home');

        yield MenuItem::section(new TranslatableMessage('admin.section.reservations'));
        yield MenuItem::linkToCrud(new TranslatableMessage('admin.reservation_series.plural'), 'fa fa-calendar-check', ReservationSeries::class);

        yield MenuItem::section(new TranslatableMessage('admin.section.resources'));
        yield MenuItem::linkToCrud(new TranslatableMessage('admin.resource.plural'), 'fa fa-door-open', Resource::class);
        yield MenuItem::linkToCrud(new TranslatableMessage('admin.resource_category.plural'), 'fa fa-tags', ResourceCategory::class);
        yield MenuItem::linkToCrud(new TranslatableMessage('admin.resource_group.plural'), 'fa fa-users-cog', ResourceGroup::class);

        yield MenuItem::section(new TranslatableMessage('admin.section.configuration'));
        yield MenuItem::linkToCrud(new TranslatableMessage('admin.schedule.plural'), 'fa fa-clock', Schedule::class);
        yield MenuItem::linkToCrud(new TranslatableMessage('admin.announcement.plural'), 'fa fa-bullhorn', Announcement::class);

        yield MenuItem::section(new TranslatableMessage('admin.section.system'))->setPermission('ROLE_SUPER_ADMIN');
        yield MenuItem::linkToCrud(new TranslatableMessage('admin.user.plural'), 'fa fa-users', User::class)
            ->setPermission('ROLE_SUPER_ADMIN');
    }
}
