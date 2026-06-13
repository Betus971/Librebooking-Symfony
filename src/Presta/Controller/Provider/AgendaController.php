<?php

namespace App\Presta\Controller\Provider;

use App\Presta\Entity\Prestataire;
use App\Presta\Entity\Session;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/presta/provider/agenda', name: 'app_presta_provider_agenda_')]
class AgendaController extends AbstractController
{
    use ProviderTrait;

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $prestataire = $this->getPrestataire($em);
        $view = $request->query->get('view', 'list'); // 'list' ou 'week'
        $dateParam = $request->query->get('date');

        // Date de début de la semaine (lundi)
        if ($dateParam) {
            $currentWeekStart = \DateTime::createFromFormat('Y-m-d', $dateParam);
        } else {
            $currentWeekStart = new \DateTime();
            $currentWeekStart->modify('monday this week');
        }

        // Générer les 7 jours de la semaine
        $daysOfWeek = [];
        for ($i = 0; $i < 7; $i++) {
            $day = (clone $currentWeekStart)->modify("+$i days");
            $daysOfWeek[] = $day;
        }

        // On récupère toutes les sessions futures (ou récentes) du prestataire qui ont au moins 1 inscrit
        $allSessions = $em->getRepository(Session::class)->createQueryBuilder('s')
            ->where('s.prestataire = :prestataire')
            ->andWhere('s.nbInscrits > 0')
            ->andWhere('s.dateDebut >= :today')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('today', new \DateTime('today'))
            ->orderBy('s.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();

        // Pour la vue semaine : organiser les sessions par jour et par heure
        $sessionsByDayAndTime = [];
        foreach ($daysOfWeek as $day) {
            $dayKey = $day->format('Y-m-d');
            $sessionsByDayAndTime[$dayKey] = [];
        }
        
        foreach ($allSessions as $session) {
            $sessionDate = $session->getDateDebut()->format('Y-m-d');
            $sessionTime = $session->getDateDebut()->format('H:i');
            
            if (isset($sessionsByDayAndTime[$sessionDate])) {
                if (!isset($sessionsByDayAndTime[$sessionDate][$sessionTime])) {
                    $sessionsByDayAndTime[$sessionDate][$sessionTime] = [];
                }
                $sessionsByDayAndTime[$sessionDate][$sessionTime][] = $session;
            }
        }

        // Pour la vue liste
        $sessions = $allSessions;

        return $this->render('presta/provider/agenda/index.html.twig', [
            'sessions' => $sessions,
            'view' => $view,
            'daysOfWeek' => $daysOfWeek,
            'currentWeekStart' => $currentWeekStart,
            'prevWeek' => (clone $currentWeekStart)->modify('-7 days'),
            'nextWeek' => (clone $currentWeekStart)->modify('+7 days'),
            'sessionsByDayAndTime' => $sessionsByDayAndTime,
        ]);
    }
}
