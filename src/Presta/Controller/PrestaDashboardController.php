<?php

namespace App\Presta\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/presta', name: 'app_presta_')]
class PrestaDashboardController extends AbstractController
{
    #[Route('/', name: 'dashboard')]
    public function index(): Response
    {
        return $this->render('presta/dashboard/index.html.twig', [
            'controller_name' => 'PrestaDashboardController',
        ]);
    }
}
