<?php

namespace App\Controller\Admin;

use App\Entity\Announcement;
use App\Entity\ReservationSeries;
use App\Entity\Resource;
use App\Entity\ResourceCategory;
use App\Entity\ResourceGroup;
use App\Entity\Schedule;
use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;

#[IsGranted('ROLE_ADMIN_RESSOURCE')]
#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        // Redirect to the Resources page by default
        $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);
        return $this->redirect($adminUrlGenerator->setController(ResourceCrudController::class)->generateUrl());
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Librebooking Admin')
            ->setFaviconPath('data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 128 128%22><rect width=%22128%22 height=%22128%22 fill=%22%234f46e5%22/></svg>');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToRoute('Retour au site', 'fa fa-home', 'app_home');
        
        yield MenuItem::section('Réservations');
        yield MenuItem::linkTo(ReservationSeriesCrudController::class, 'Historique', 'fas fa-calendar-check');
        
        yield MenuItem::section('Ressources');
        yield MenuItem::linkTo(ResourceCrudController::class, 'Salles / Objets', 'fas fa-door-open');
        yield MenuItem::linkTo(ResourceCategoryCrudController::class, 'Catégories', 'fas fa-tags');
        yield MenuItem::linkTo(ResourceGroupCrudController::class, 'Groupes d\'approbation', 'fas fa-users-cog');
        
        yield MenuItem::section('Configuration');
        yield MenuItem::linkTo(ScheduleCrudController::class, 'Plannings', 'fas fa-clock');
        yield MenuItem::linkTo(AnnouncementCrudController::class, 'Annonces', 'fas fa-bullhorn');
        
        yield MenuItem::section('Système')->setPermission('ROLE_SUPER_ADMIN');
        yield MenuItem::linkTo(UserCrudController::class, 'Utilisateurs', 'fas fa-users')->setPermission('ROLE_SUPER_ADMIN');
    }
}
