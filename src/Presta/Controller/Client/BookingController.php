<?php

namespace App\Presta\Controller\Client;

use App\Entity\User;
use App\Presta\Entity\Inscription;
use App\Presta\Entity\Service;
use App\Presta\Entity\Session;
use App\Presta\Notification\PrestaNotifier;
use App\Presta\Repository\InscriptionRepository;
use App\Presta\Repository\SessionRepository;
use App\Presta\Service\CreneauGenerator;
use App\Presta\Service\IndividualBookingManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/presta/c/booking', name: 'app_presta_client_booking_')]
class BookingController extends AbstractController
{
    public function __construct(
        private readonly SessionRepository $sessionRepository,
        private readonly CreneauGenerator $creneauGenerator,
    ) {
    }

    private function getClient(): User
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour réserver.');
        }

        return $user;
    }

    #[Route('/group/{id}', name: 'group', methods: ['GET'])]
    public function groupSessions(Service $service, Request $request): Response
    {
        if ($service->getType() !== Service::TYPE_GROUPE) {
            throw $this->createNotFoundException('Ce service n\'est pas un service de groupe.');
        }

        $view = $request->query->get('view', 'list'); // 'list' ou 'calendar'
        $weekParam = $request->query->get('week');

        // Date de début de la semaine (lundi)
        if ($weekParam) {
            $currentWeekStart = \DateTime::createFromFormat('Y-m-d', $weekParam) ?: new \DateTime();
        } else {
            $currentWeekStart = new \DateTime();
        }
        $currentWeekStart->modify('monday this week');

        // Générer les 7 jours de la semaine
        $daysOfWeek = [];
        for ($i = 0; $i < 7; $i++) {
            $daysOfWeek[] = (clone $currentWeekStart)->modify("+$i days");
        }

        // Toutes les sessions futures pour ce service (1 requête, dans le repo).
        $allSessions = $this->sessionRepository->findFutureByService($service);

        // Grouper les sessions par jour pour la vue calendrier
        $sessionsByDay = [];
        foreach ($daysOfWeek as $day) {
            $sessionsByDay[$day->format('Y-m-d')] = [];
        }
        foreach ($allSessions as $session) {
            $sessionDate = $session->getDateDebut()->format('Y-m-d');
            if (isset($sessionsByDay[$sessionDate])) {
                $sessionsByDay[$sessionDate][] = $session;
            }
        }

        return $this->render('presta/client/booking/group.html.twig', [
            'service' => $service,
            'sessions' => $allSessions,
            'sessionsByDay' => $sessionsByDay,
            'daysOfWeek' => $daysOfWeek,
            'currentWeekStart' => $currentWeekStart,
            'prevWeek' => (clone $currentWeekStart)->modify('-7 days'),
            'nextWeek' => (clone $currentWeekStart)->modify('+7 days'),
            'view' => $view,
        ]);
    }

    #[Route('/group/book/{id}', name: 'group_book', methods: ['POST'])]
    public function bookGroup(Session $session, Request $request, EntityManagerInterface $em, InscriptionRepository $inscriptions, PrestaNotifier $notifier): Response
    {
        if ($this->isCsrfTokenValid('book_session'.$session->getId(), $request->request->get('_token'))) {

            $client = $this->getClient();

            // Vérifier si le client est déjà inscrit
            $existing = $inscriptions->findOneBy([
                'session' => $session,
                'client' => $client,
            ]);

            if ($existing) {
                $this->addFlash('warning', 'Vous êtes déjà inscrit à cette séance (ou sur liste d\'attente).');
            } else {
                $needsApproval = $session->getService()->isRequiresApproval();
                
                // Vérifier la capacité pour liste d'attente
                $isWaitlisted = $session->getNbInscrits() >= $session->getService()->getCapaciteMax();

                $inscription = new Inscription();
                $inscription->setSession($session);
                $inscription->setClient($client);
                
                if ($isWaitlisted) {
                    $inscription->setStatut(Inscription::STATUT_WAITLIST);
                } else {
                    $inscription->setStatut($needsApproval ? Inscription::STATUT_PENDING : Inscription::STATUT_CONFIRMED);
                    // On n'incrémente le nombre d'inscrits que s'il n'est pas sur liste d'attente
                    // (Les personnes sur liste d'attente ne prennent pas de place)
                    $session->setNbInscrits($session->getNbInscrits() + 1);
                }

                $em->persist($inscription);
                $em->flush();

                if ($isWaitlisted) {
                    $this->addFlash('warning', 'Cette séance est complète. Vous avez été ajouté à la liste d\'attente.');
                } else {
                    if (!$needsApproval) {
                        // Inscription confirmée immédiatement → e-mail + .ics.
                        try { $notifier->confirmed($inscription); } catch (\Throwable) {}
                    }

                    $this->addFlash('success', $needsApproval
                        ? 'Votre demande d\'inscription a été envoyée. Elle est en attente de validation par le prestataire.'
                        : 'Votre inscription à la séance a été confirmée !');
                }
            }
        }

        return $this->redirectToRoute('app_presta_client_prestataire_show', ['id' => $session->getPrestataire()->getId()]);
    }

    #[Route('/individual/{id}', name: 'individual', methods: ['GET'])]
    public function individualSlots(Service $service, Request $request): Response
    {
        if ($service->getType() !== Service::TYPE_INDIVIDUEL) {
            throw $this->createNotFoundException('Ce service n\'est pas un service individuel.');
        }

        $dateParam = $request->query->get('date');
        $view = $request->query->get('view', 'day'); // 'day', 'week', ou 'month'
        $date = $dateParam ? \DateTime::createFromFormat('Y-m-d', $dateParam) : new \DateTime();
        if (!$date) {
            $date = new \DateTime();
        }

        // Fenêtre glissante : le client ne peut ni voir ni naviguer au-delà de
        // l'horizon du prestataire. Si une date trop lointaine est demandée
        // (URL forcée), on la ramène à l'horizon.
        $maxDate = $service->getPrestataire()->getMaxBookingDate();
        if ($date->format('Y-m-d') > $maxDate->format('Y-m-d')) {
            $date = new \DateTime($maxDate->format('Y-m-d'));
        }

        $daysOfWeek = [];
        $creneauxByDay = [];
        $calendarDays = [];
        $creneauxByMonthDay = [];

        if ($view === 'week') {
            $startOfWeek = (clone $date)->modify('monday this week');
            $endOfWeek = (clone $startOfWeek)->modify('+6 days');

            // 1 seul chargement pour les 7 jours (au lieu de 7×3 requêtes).
            $creneauxByDay = $this->creneauGenerator->generateForRange($service, $startOfWeek, $endOfWeek);

            for ($i = 0; $i < 7; $i++) {
                $daysOfWeek[] = (clone $startOfWeek)->modify("+$i days");
            }
        } elseif ($view === 'month') {
            $startOfMonth = (clone $date)->modify('first day of this month');
            $startGrid = clone $startOfMonth;
            if ($startGrid->format('N') != 1) {
                $startGrid->modify('previous monday');
            }

            $endOfMonth = (clone $date)->modify('last day of this month');
            $endGrid = clone $endOfMonth;
            if ($endGrid->format('N') != 7) {
                $endGrid->modify('next sunday');
            }

            // 1 seul chargement pour toute la grille du mois (~35-42 jours)
            // → 3 requêtes au total au lieu de ~3 par jour (le bug des 111 req.).
            $creneauxByRange = $this->creneauGenerator->generateForRange($service, $startGrid, $endGrid);

            $currentDay = clone $startGrid;
            while ($currentDay <= $endGrid) {
                $key = $currentDay->format('Y-m-d');
                $calendarDays[] = clone $currentDay;
                $creneauxByMonthDay[$key] = count($creneauxByRange[$key] ?? []);
                $currentDay->modify('+1 day');
            }
        }

        // Vue jour : créneaux du jour sélectionné.
        $creneaux = $this->creneauGenerator->generateForDate($service, $date);

        // Navigation
        $prevMonth = (clone $date)->modify('first day of last month');
        $nextMonth = (clone $date)->modify('first day of next month');

        $nextAvailableDate = null;
        if ($view === 'day' && empty($creneaux)) {
            $nextAvailableDate = $this->creneauGenerator->findNextAvailableDate($service, $date);
        } elseif ($view === 'week') {
            $totalCreneaux = 0;
            foreach ($creneauxByDay as $dayCreneaux) {
                $totalCreneaux += count($dayCreneaux);
            }
            if ($totalCreneaux === 0) {
                $nextAvailableDate = $this->creneauGenerator->findNextAvailableDate($service, $date);
            }
        }

        return $this->render('presta/client/booking/individual.html.twig', [
            'service' => $service,
            'currentDate' => $date,
            'prevDate' => (clone $date)->modify('-1 day'),
            'nextDate' => (clone $date)->modify('+1 day'),
            'prevWeek' => (clone $date)->modify('-7 days'),
            'nextWeek' => (clone $date)->modify('+7 days'),
            'prevMonth' => $prevMonth,
            'nextMonth' => $nextMonth,
            'creneaux' => $creneaux,
            'creneauxByDay' => $creneauxByDay,
            'daysOfWeek' => $daysOfWeek,
            'calendarDays' => $calendarDays,
            'creneauxByMonthDay' => $creneauxByMonthDay,
            'view' => $view,
            'maxDate' => $maxDate,
            'nextAvailableDate' => $nextAvailableDate,
        ]);
    }

    #[Route('/individual/book/{id}', name: 'individual_book', methods: ['POST'])]
    public function bookIndividual(Service $service, Request $request, IndividualBookingManager $booking, PrestaNotifier $notifier): Response
    {
        $timeStr = $request->request->get('time'); // ex: 2026-06-10 14:00

        if ($this->isCsrfTokenValid('book_individual'.$service->getId(), $request->request->get('_token')) && $timeStr) {

            $dateDebut = \DateTime::createFromFormat('Y-m-d H:i', $timeStr);
            if ($dateDebut && $dateDebut > $service->getPrestataire()->getMaxBookingDate()) {
                // Fenêtre glissante : au-delà de l'horizon, on refuse (garde-fou
                // serveur, en plus du filtrage à l'affichage).
                $this->addFlash('error', sprintf(
                    'Les réservations ne sont ouvertes que jusqu\'au %s.',
                    $service->getPrestataire()->getMaxBookingDate()->format('d/m/Y')
                ));

                return $this->redirectToRoute('app_presta_client_prestataire_show', ['id' => $service->getPrestataire()->getId()]);
            }
            if ($dateDebut) {
                // Réservation SÛRE : verrou + re-check de conflit sous verrou
                // (anti double-réservation), déléguée au service métier.
                try {
                    $inscription = $booking->book($service, $this->getClient(), $dateDebut);
                } catch (\DomainException $e) {
                    if ('active_booking_exists' === $e->getMessage()) {
                        $this->addFlash('error', 'Vous avez déjà un rendez-vous en cours chez ce prestataire. Merci de l\'annuler (ou d\'attendre qu\'il soit passé) avant d\'en prendre un nouveau.');

                        return $this->redirectToRoute('app_presta_client_prestataire_show', ['id' => $service->getPrestataire()->getId()]);
                    }
                    throw $e;
                }
                if ($inscription === null) {
                    $this->addFlash('error', 'Désolé, ce créneau vient d\'être réservé. Merci d\'en choisir un autre.');
                } elseif ($inscription->isPending()) {
                    $this->addFlash('success', 'Votre demande de rendez-vous du ' . $dateDebut->format('d/m/Y à H:i') . ' a bien été envoyée. Elle est en attente de validation par le prestataire.');
                } else {
                    // RDV confirmé immédiatement → e-mail de confirmation + .ics.
                    try { $notifier->confirmed($inscription); } catch (\Throwable) {}
                    $this->addFlash('success', 'Votre rendez-vous a été confirmé le ' . $dateDebut->format('d/m/Y à H:i') . ' !');
                }
            }
        }

        return $this->redirectToRoute('app_presta_client_prestataire_show', ['id' => $service->getPrestataire()->getId()]);
    }
}
