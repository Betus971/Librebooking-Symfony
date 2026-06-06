<?php

namespace App\Controller;

use App\Repository\ResourceRepository;
use App\Util\WeekHelper;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CalendarController extends AbstractController
{
    #[Route('/calendar', name: 'app_calendar')]
    public function index(Request $request,ResourceRepository $resourceRepo, WeekHelper $weeks): Response
    {

        $isoWeek = $request->query->get('week') ?? $weeks->isoWeekString(new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')));
        $wk = $weeks->fromIsoWeek($isoWeek);

        return $this->render('calendar/index.html.twig', [
            'resources'       => $resourceRepo->findAll(),
            'resourceId' => null,
            'currentResourceId' => null,
            'isoWeek'    => $wk->toString(),
            'weekStart'  => $wk->startOfWeek('Europe/Paris')->format('Y-m-d'),
            'prevWeek'   => $wk->prev()->toString(),
            'nextWeek'   => $wk->next()->toString(),
            'pageTitle'  => "Planning — toutes ressources",


        ]);

    }

    /**
     * ═════════════════════════════════════════════════════════════════════
     *  CALENDAR V2  — restyle UX (option A) en template parallèle
     *
     *  Objectif : tester un nouveau rendu visuel FullCalendar conforme à la
     *  maquette (pills colorées, jour courant en bleu foncé, légende des
     *  catégories, hint « Cliquez sur un créneau… ») SANS toucher à la
     *  route `/calendar` de production.
     *
     *  Même source d'événements (`/fc-load-events`), même subscriber, même
     *  entités — seul le template + la CSS changent.
     * ═════════════════════════════════════════════════════════════════════
     */
    #[Route('/calendar/v2', name: 'app_calendar_v2')]
    public function indexV2(
        Request $request,
        ResourceRepository $resourceRepo,
        WeekHelper $weeks
    ): Response {
        $isoWeek = $request->query->get('week')
            ?? $weeks->isoWeekString(new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')));
        $wk = $weeks->fromIsoWeek($isoWeek);

        return $this->render('calendar/index_v2.html.twig', [
            'resources'         => $resourceRepo->findAll(),
            'resourceId'        => null,
            'currentResourceId' => null,
            'isoWeek'           => $wk->toString(),
            'weekStart'         => $wk->startOfWeek('Europe/Paris')->format('Y-m-d'),
            'prevWeek'          => $wk->prev()->toString(),
            'nextWeek'          => $wk->next()->toString(),
            'pageTitle'         => "Planning — Aperçu V2",
        ]);
    }

    #[Route('/calendar/{resourceId}', name: 'app_calendar_resource', requirements: ['resourceId' => '\d+'], methods: ['GET'])]
    public function calendar(?int $resourceId, Request $request, WeekHelper $weeks, ResourceRepository $resources): Response
    {
        $tz   = new \DateTimeZone('Europe/Paris');
        $view = $request->query->get('view', 'week'); // 'day' | 'week' | 'month'

        $isoWeek = $request->query->get('week') ?? $weeks->isoWeekString(new \DateTimeImmutable('now', $tz));
        $wk      = $weeks->fromIsoWeek($isoWeek);

        $month     = $request->query->get('month') ?? $wk->startOfWeek()->format('Y-m');
        $monthDate = (new \DateTimeImmutable($month.'-01', $tz))->setTime(0, 0);

        $resource  = $resourceId ? $resources->find($resourceId) : null;

        return $this->render('calendar/index.html.twig', [
            'pageTitle'   => $resource ? sprintf('Planning — %s', $resource->getName()) : 'Planning — toutes ressources',
            'resourceId'  => $resource?->getId(),
            'resources'   => $resources->findAll(), // <- toujours fourni au Twig
            'isoWeek'     => $wk->toString(),
            'month'       => $month,
            'initialDate' => $view === 'month' ? $monthDate->format('Y-m-d') : $wk->startOfWeek()->format('Y-m-d'),
            'currentView' => $view,
            'weekStart'   => $wk->startOfWeek()->format('Y-m-d'),
            'prevWeek'    => $wk->prev()->toString(),
            'nextWeek'    => $wk->next()->toString(),
            'prevMonth'   => $monthDate->modify('-1 month')->format('Y-m'),
            'nextMonth'   => $monthDate->modify('+1 month')->format('Y-m'),
            'currentResourceId' => $resourceId,

        ]);
    }
    /**
     * ICS Feed for Thunderbird / Outlook / Google Calendar
     * Displays Resources/Reservations
     */
    #[Route('/calendar/feed.ics', name: 'app_calendar_feed', methods: ['GET'])]
    public function feed(
        ResourceRepository $resourceRepository
        // ReservationRepository $reservationRepository // <-- Injection du repo de réservations
    ): Response
    {
        $icsContent = "BEGIN:VCALENDAR\r\n";
        $icsContent .= "VERSION:2.0\r\n";
        $icsContent .= "PRODID:-//Librebooking//FR\r\n";
        $icsContent .= "CALSCALE:GREGORIAN\r\n";
        $icsContent .= "METHOD:PUBLISH\r\n";

        // ==========================================
        // 1. GESTION DES RESSOURCES (Salles, etc.)
        // ==========================================
        // Si vous avez une entité 'Reservation' liée à une 'Resource'
        /*
        $reservations = $reservationRepository->findAll(); // Récupérez vos réservations

        foreach ($reservations as $resa) {
            // Adaptez les méthodes (getStart, getEnd, getResource...)
            $resourceName = $resa->getResource() ? $resa->getResource()->getName() : 'Ressource';
            $summary = "Réservé : " . $resourceName . " (" . $resa->getUser()->getNom() . ")";

            $icsContent .= $this->createIcsEvent(
                'reservation-' . $resa->getId(),
                $resa->getDateDebut(), // Vos dates de début
                $resa->getDateFin(),   // Vos dates de fin
                $summary,
                "Réservé par : " . $resa->getUser()->getEmail()
            );
        }
        */

        // ==========================================
        // 3. (OPTIONNEL) DISPONIBILITÉ DES RESSOURCES
        // ==========================================
        // Si vous n'avez pas de réservations mais voulez afficher les ressources comme événements (ex: ouverture)
        // Vous pouvez boucler sur $resourceRepository->findAll() ici.

        $icsContent .= "END:VCALENDAR";

        return new Response($icsContent, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="planning_complet.ics"',
        ]);
    }

    /**
     * Fonction utilitaire pour générer un bloc VEVENT proprement
     */
    private function createIcsEvent(string $uid, \DateTimeInterface $start, \DateTimeInterface $end, string $summary, string $description = ''): string
    {
        // Formatage des dates UTC pour ICS (YmdTHisZ est le standard le plus sûr)
        $dtStart = $start->format('Ymd\THis');
        $dtEnd   = $end->format('Ymd\THis');
        $now     = date('Ymd\THis');

        // Nettoyage des textes
        $summary = $this->escapeIcs($summary);
        $description = $this->escapeIcs($description);

        return "BEGIN:VEVENT\r\n" .
            "UID:{$uid}@monapp.local\r\n" .
            "DTSTAMP:{$now}\r\n" .
            "DTSTART:{$dtStart}\r\n" .
            "DTEND:{$dtEnd}\r\n" .
            "SUMMARY:{$summary}\r\n" .
            "DESCRIPTION:{$description}\r\n" .
            "END:VEVENT\r\n";
    }

    private function escapeIcs(string $string): string
    {
        // P1.7 — Robust RFC 5545 escaping against CRLF injection.
        // Order is important: we treat backslash first, then
        // CRLF/CR/LF -> "\n", then , and ;.
        // The old version replaced \r with empty strings.
        return str_replace(
            ['\\',   "\r\n", "\r",  "\n",  ',',   ';'],
            ['\\\\', '\\n',  '\\n', '\\n', '\\,', '\\;'],
            $string
        );
    }
}
