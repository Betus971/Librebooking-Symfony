<?php

namespace App\Presta\Controller\Client;

use App\Presta\Entity\Inscription;
use App\Presta\Entity\Service;
use App\Presta\Entity\Session;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/presta/c/booking', name: 'app_presta_client_booking_')]
class BookingController extends AbstractController
{
    private function getClient(): \App\Entity\User
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour réserver.');
        }
        return $user;
    }

    #[Route('/group/{id}', name: 'group', methods: ['GET'])]
    public function groupSessions(Service $service, Request $request, EntityManagerInterface $em): Response
    {
        if ($service->getType() !== Service::TYPE_GROUPE) {
            throw $this->createNotFoundException('Ce service n\'est pas un service de groupe.');
        }

        $view = $request->query->get('view', 'list'); // 'list' ou 'calendar'
        $weekParam = $request->query->get('week');

        // Date de début de la semaine (lundi)
        if ($weekParam) {
            $currentWeekStart = \DateTime::createFromFormat('Y-m-d', $weekParam);
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

        // On cherche toutes les sessions futures pour ce service
        $allSessions = $em->getRepository(Session::class)->createQueryBuilder('s')
            ->where('s.service = :service')
            ->andWhere('s.dateDebut > :now')
            ->setParameter('service', $service)
            ->setParameter('now', new \DateTime())
            ->orderBy('s.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();

        // Grouper les sessions par jour pour la vue calendrier
        $sessionsByDay = [];
        foreach ($daysOfWeek as $day) {
            $dayKey = $day->format('Y-m-d');
            $sessionsByDay[$dayKey] = [];
        }

        foreach ($allSessions as $session) {
            $sessionDate = $session->getDateDebut()->format('Y-m-d');
            if (isset($sessionsByDay[$sessionDate])) {
                $sessionsByDay[$sessionDate][] = $session;
            }
        }

        // Pour la vue liste (par défaut)
        $sessions = $allSessions;

        return $this->render('presta/client/booking/group.html.twig', [
            'service' => $service,
            'sessions' => $sessions,
            'sessionsByDay' => $sessionsByDay,
            'daysOfWeek' => $daysOfWeek,
            'currentWeekStart' => $currentWeekStart,
            'prevWeek' => (clone $currentWeekStart)->modify('-7 days'),
            'nextWeek' => (clone $currentWeekStart)->modify('+7 days'),
            'view' => $view,
        ]);
    }

    #[Route('/group/book/{id}', name: 'group_book', methods: ['POST'])]
    public function bookGroup(Session $session, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('book_session'.$session->getId(), $request->request->get('_token'))) {

            // Vérifier la capacité
            if ($session->getNbInscrits() >= $session->getService()->getCapaciteMax()) {
                $this->addFlash('error', 'Désolé, cette séance est déjà complète.');
                return $this->redirectToRoute('app_presta_client_booking_group', ['id' => $session->getService()->getId()]);
            }

            $client = $this->getClient();

            // Vérifier si le client est déjà inscrit
            $existing = $em->getRepository(Inscription::class)->findOneBy([
                'session' => $session,
                'client' => $client,
            ]);

            if ($existing) {
                $this->addFlash('warning', 'Vous êtes déjà inscrit à cette séance.');
            } else {
                $inscription = new Inscription();
                $inscription->setSession($session);
                $inscription->setClient($client);
                $inscription->setStatut('CONFIRMED');

                // Incrémenter le nombre d'inscrits
                $session->setNbInscrits($session->getNbInscrits() + 1);

                $em->persist($inscription);
                $em->flush();

                $this->addFlash('success', 'Votre inscription à la séance a été confirmée !');
            }
        }

        return $this->redirectToRoute('app_presta_client_prestataire_show', ['id' => $session->getPrestataire()->getId()]);
    }

    #[Route('/individual/{id}', name: 'individual', methods: ['GET'])]
    public function individualSlots(Service $service, Request $request, EntityManagerInterface $em): Response
    {
        if ($service->getType() !== Service::TYPE_INDIVIDUEL) {
            throw $this->createNotFoundException('Ce service n\'est pas un service individuel.');
        }

        $prestataire = $service->getPrestataire();

        // Date demandée ou aujourd'hui
        $dateParam = $request->query->get('date');
        $view = $request->query->get('view', 'day'); // 'day' ou 'week'
        $date = $dateParam ? \DateTime::createFromFormat('Y-m-d', $dateParam) : new \DateTime();
        if (!$date) {
            $date = new \DateTime();
        }

        // Pour la vue semaine : calculer les 7 jours
        $daysOfWeek = [];
        $creneauxByDay = [];
        if ($view === 'week') {
            $startOfWeek = (clone $date)->modify('monday this week');
            for ($i = 0; $i < 7; $i++) {
                $day = (clone $startOfWeek)->modify("+$i days");
                $daysOfWeek[] = $day;
                $creneauxByDay[$day->format('Y-m-d')] = $this->generateCreneauxForDate($service, $day, $em);
            }
        }

        // Pour la vue jour : calculer les créneaux
        $creneaux = $this->generateCreneauxForDate($service, $date, $em);

        // Préparer les jours précédent/suivant pour la navigation
        $prevDate = (clone $date)->modify('-1 day');
        $nextDate = (clone $date)->modify('+1 day');

        // Pour la vue semaine
        $prevWeek = (clone $date)->modify('-7 days');
        $nextWeek = (clone $date)->modify('+7 days');

        return $this->render('presta/client/booking/individual.html.twig', [
            'service' => $service,
            'currentDate' => $date,
            'prevDate' => $prevDate,
            'nextDate' => $nextDate,
            'prevWeek' => $prevWeek,
            'nextWeek' => $nextWeek,
            'creneaux' => $creneaux,
            'creneauxByDay' => $creneauxByDay,
            'daysOfWeek' => $daysOfWeek,
            'view' => $view,
        ]);
    }

    /**
     * Génère les créneaux disponibles pour un service individuel à une date donnée
     */
    private function generateCreneauxForDate(Service $service, \DateTimeInterface $date, EntityManagerInterface $em): array
    {
        $prestataire = $service->getPrestataire();
        $jourSemaine = (int)$date->format('N');
        $duree = $service->getDureeMinutes();

        // Récupérer les plages horaires du prestataire pour ce jour
        $plages = $em->getRepository(\App\Presta\Entity\PlageHoraire::class)->findBy([
            'prestataire' => $prestataire,
            'jourSemaine' => $jourSemaine,
        ]);

        // Récupérer les sessions déjà réservées pour ce prestataire ce jour-là
        $dateStart = clone $date;
        $dateStart->setTime(0, 0, 0);
        $dateEnd = clone $date;
        $dateEnd->setTime(23, 59, 59);

        $existingSessions = $em->getRepository(Session::class)->createQueryBuilder('s')
            ->where('s.prestataire = :prestataire')
            ->andWhere('s.dateDebut >= :dateStart')
            ->andWhere('s.dateDebut <= :dateEnd')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('dateStart', $dateStart)
            ->setParameter('dateEnd', $dateEnd)
            ->getQuery()
            ->getResult();

        $creneaux = [];

        foreach ($plages as $plage) {
            $currentStart = clone $date;
            $currentStart->setTime((int)$plage->getHeureDebut()->format('H'), (int)$plage->getHeureDebut()->format('i'));

            $endPlage = clone $date;
            $endPlage->setTime((int)$plage->getHeureFin()->format('H'), (int)$plage->getHeureFin()->format('i'));

            while ($currentStart < $endPlage) {
                $currentEnd = clone $currentStart;
                $currentEnd->modify('+' . $duree . ' minutes');

                // Si le créneau dépasse la fin de la plage, on l'ignore
                if ($currentEnd > $endPlage) {
                    break;
                }

                // Ne pas proposer de créneaux dans le passé
                if ($currentStart > new \DateTime()) {
                    // Vérifier s'il y a un chevauchement avec une session existante
                    $overlap = false;
                    foreach ($existingSessions as $es) {
                        // Chevauchement si (Debut1 < Fin2) ET (Debut2 < Fin1)
                        if ($currentStart < $es->getDateFin() && $es->getDateDebut() < $currentEnd) {
                            $overlap = true;
                            break;
                        }
                    }

                    if (!$overlap) {
                        $creneaux[] = [
                            'start' => clone $currentStart,
                            'end' => clone $currentEnd,
                            'startStr' => $currentStart->format('Y-m-d H:i'),
                        ];
                    }
                }

                $currentStart->modify('+' . $duree . ' minutes');
            }
        }

        return $creneaux;
    }

    #[Route('/individual/book/{id}', name: 'individual_book', methods: ['POST'])]
    public function bookIndividual(Service $service, Request $request, EntityManagerInterface $em): Response
    {
        $timeStr = $request->request->get('time'); // ex: 2026-06-10 14:00

        if ($this->isCsrfTokenValid('book_individual'.$service->getId(), $request->request->get('_token')) && $timeStr) {

            $dateDebut = \DateTime::createFromFormat('Y-m-d H:i', $timeStr);
            if ($dateDebut) {
                $dateFin = clone $dateDebut;
                $dateFin->modify('+' . $service->getDureeMinutes() . ' minutes');

                // Dans un système complet, il faudrait re-vérifier la dispo exacte ici avant d'enregistrer
                // (Double booking prevention)

                $client = $this->getClient();

                // Création d'une session dédiée pour ce RDV individuel
                $session = new Session();
                $session->setPrestataire($service->getPrestataire());
                $session->setService($service);
                $session->setDateDebut($dateDebut);
                $session->setDateFin($dateFin);
                $session->setNbInscrits(1);

                $em->persist($session);

                $inscription = new Inscription();
                $inscription->setSession($session);
                $inscription->setClient($client);
                $inscription->setStatut('CONFIRMED');

                $em->persist($inscription);
                $em->flush();

                $this->addFlash('success', 'Votre rendez-vous a été confirmé le ' . $dateDebut->format('d/m/Y à H:i') . ' !');
            }
        }

        return $this->redirectToRoute('app_presta_client_prestataire_show', ['id' => $service->getPrestataire()->getId()]);
    }
}
