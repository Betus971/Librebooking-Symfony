<?php

namespace App\Presta\Controller\Client;

use App\Presta\Entity\Prestataire;
use App\Presta\Entity\Service;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/presta/c/prestataire', name: 'app_presta_client_prestataire_')]
class PrestataireController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        // On récupère tous les prestataires ACTIFS pour l'annuaire
        $prestataires = $em->getRepository(Prestataire::class)->findBy(['isActive' => true]);

        return $this->render('presta/client/prestataire/index.html.twig', [
            'prestataires' => $prestataires,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Prestataire $prestataire, EntityManagerInterface $em): Response
    {
        if (!$prestataire->isActive()) {
            throw $this->createNotFoundException('Ce prestataire est actuellement inactif.');
        }

        // On récupère les services ACTIFS du prestataire
        $services = $em->getRepository(Service::class)->findBy([
            'prestataire' => $prestataire,
            'isActive' => true,
        ]);

        return $this->render('presta/client/prestataire/show.html.twig', [
            'prestataire' => $prestataire,
            'services' => $services,
        ]);
    }
}
