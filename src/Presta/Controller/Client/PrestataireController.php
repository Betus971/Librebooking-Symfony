<?php

namespace App\Presta\Controller\Client;

use App\Presta\Entity\Prestataire;
use App\Presta\Repository\PrestataireRepository;
use App\Presta\Repository\ServiceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/presta/c/prestataire', name: 'app_presta_client_prestataire_')]
class PrestataireController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(PrestataireRepository $prestataires): Response
    {
        return $this->render('presta/client/prestataire/index.html.twig', [
            'prestataires' => $prestataires->findActive(),
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Prestataire $prestataire, ServiceRepository $services): Response
    {
        if (!$prestataire->isActive()) {
            throw $this->createNotFoundException('Ce prestataire est actuellement inactif.');
        }

        return $this->render('presta/client/prestataire/show.html.twig', [
            'prestataire' => $prestataire,
            'services' => $services->findActiveByPrestataire($prestataire),
        ]);
    }
}
