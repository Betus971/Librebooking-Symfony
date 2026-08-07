<?php

namespace App\Presta\Controller\Provider;

use App\Presta\Repository\InscriptionRepository;
use App\Presta\Service\PrestataireResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/presta/provider/dashboard', name: 'app_presta_provider_dashboard_')]
#[IsGranted('ROLE_PRESTATAIRE')]
class DashboardController extends AbstractController
{
    public function __construct(
        private readonly PrestataireResolver $prestataireResolver,
        private readonly InscriptionRepository $inscriptionRepository,
    ) {
    }

    #[Route('/', name: 'index')]
    public function index(): Response
    {
        $prestataire = $this->prestataireResolver->getForCurrentUser();
        $stats = $this->inscriptionRepository->getProviderStats($prestataire);

        return $this->render('presta/provider/dashboard/index.html.twig', [
            'stats' => $stats,
        ]);
    }
}
