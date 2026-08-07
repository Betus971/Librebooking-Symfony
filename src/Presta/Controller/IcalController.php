<?php

namespace App\Presta\Controller;

use App\Presta\Repository\PrestataireRepository;
use App\Presta\Repository\SessionRepository;
use App\Service\IcsGeneratorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class IcalController extends AbstractController
{
    #[Route('/presta/ical/{token}', name: 'app_presta_ical_feed', methods: ['GET'])]
    public function feed(
        string $token, 
        PrestataireRepository $prestataireRepository,
        SessionRepository $sessionRepository,
        IcsGeneratorService $icsGenerator
    ): Response
    {
        $prestataire = $prestataireRepository->findOneBy(['icalToken' => $token]);

        if (!$prestataire || !$token) {
            throw $this->createNotFoundException('Lien iCal invalide.');
        }

        // On récupère les sessions à venir (celles de l'agenda)
        $sessions = $sessionRepository->findUpcomingForAgenda($prestataire);
        
        $icsContent = $icsGenerator->generateForPrestaAgenda($sessions);

        return new Response($icsContent, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="agenda-presta.ics"',
        ]);
    }
}
