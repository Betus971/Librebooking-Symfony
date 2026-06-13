<?php

namespace App\Controller;

use App\Repository\ReservationInstanceRepository;
use App\Repository\ResourceRepository;
use App\Service\IcsGeneratorService;
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
    public function feed(IcsGeneratorService $icsGenerator): Response
    {
        // Aucun événement pour l'instant (cf. TODO ci-dessous) : on expose un
        // calendrier vide mais valide. Lorsque les réservations seront publiées
        // dans le flux, construire ici le tableau d'événements et le passer à
        // $icsGenerator->generate($events).
        $icsContent = $icsGenerator->generate();

        return new Response($icsContent, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="planning_complet.ics"',
        ]);
    }

    /**
     * Flux iCal abonnable d'UNE ressource, identifiée par son public_id
     * (URL non énumérable). Couvre une fenêtre glissante (passé proche → +90 j).
     */
    #[Route('/calendar/feed/{publicId}.ics', name: 'app_calendar_feed_resource', methods: ['GET'])]
    public function resourceFeed(
        string $publicId,
        ResourceRepository $resources,
        ReservationInstanceRepository $instances,
        IcsGeneratorService $icsGenerator,
    ): Response {
        $resource = $resources->findOneBy(['publicId' => $publicId]);
        if (null === $resource) {
            throw $this->createNotFoundException('Ressource introuvable.');
        }

        $from = (new \DateTimeImmutable('today'))->modify('-7 days');
        $to   = (new \DateTimeImmutable('today'))->modify('+90 days');

        $events  = $instances->findOverlappingForResource((int) $resource->getId(), $from, $to);
        $content = $icsGenerator->generateForReservations($events);

        return new Response($content, 200, [
            'Content-Type'        => 'text/calendar; charset=utf-8',
            'Content-Disposition' => sprintf('attachment; filename="resource-%d.ics"', $resource->getId()),
        ]);
    }
}
