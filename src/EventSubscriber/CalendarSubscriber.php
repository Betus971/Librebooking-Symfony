<?php
// src/EventSubscriber/CalendarSubscriber.php

namespace App\EventSubscriber;

use App\Repository\ReservationInstanceRepository;
use App\Repository\ResourceRepository;
use App\Repository\TimeBlockRepository;
use App\Util\WeekHelper;
use CalendarBundle\Entity\Event as FcEvent;
use CalendarBundle\Event\SetDataEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class CalendarSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ReservationInstanceRepository $instances,
        private readonly ResourceRepository $resources,
        private readonly TimeBlockRepository $timeBlocks,
        private readonly WeekHelper $weeks
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [ SetDataEvent::class => 'onCalendarSetData' ];
    }

    public function onCalendarSetData(SetDataEvent $event): void
    {$start = $event->getStart();
        $end   = $event->getEnd();
        $filters = $event->getFilters();

        $filterArray = $filters;
        if (isset($filters['filters']) && is_string($filters['filters'])) {
            $decoded = json_decode($filters['filters'], true);
            if (is_array($decoded)) {
                $filterArray = array_merge($filterArray, $decoded);
            }
        }
        $resourceId = isset($filterArray['resourceId']) && $filterArray['resourceId'] !== '' ? (int)$filterArray['resourceId'] : null;

        // ----- 1) Reservations (instances) -----
        if ($resourceId) {
            $reservations = $this->instances->findOverlappingForResource($resourceId, $start, $end);
        } else {
            $reservations = $this->instances->findOverlappingAllResources($start, $end);
        }

        foreach ($reservations as $r) {
            $series = method_exists($r, 'getSeries') ? $r->getSeries() : null;
            $title = $series && $series->getTitle() ? $series->getTitle() : ($r->getReferenceNumber() ?? 'Réservation');

            // --- NOUVEAUTÉ : On cherche les ressources liées et leur couleur ---
            $resourceNames = [];
            $color = null;

            // Une petite palette de couleurs officielles DSFR pour les salles sans couleur
            $dsfrPalette = ['#000091', '#e1000f', '#007022', '#d64d00', '#8585f6', '#00a95f', '#66673d', '#4c4c4c'];

            if ($series && method_exists($series, 'getReservationResources')) {
                foreach ($series->getReservationResources() as $rr) {
                    $res = $rr->getResource();
                    if ($res) {
                        $resourceNames[] = $res->getName();

                        // Si la salle a une couleur en base de données, on la prend
                        if ($res->getColor()) {
                            $color = $res->getColor();
                        }
                        // SINON, on lui attribue une couleur fixe de la palette en fonction de son ID !
                        elseif (!$color) {
                            $colorIndex = $res->getId() % count($dsfrPalette);
                            $color = $dsfrPalette[$colorIndex];
                        }
                    }
                }
            }

            // Si on est sur "Toutes les ressources", on ajoute les noms des salles au titre !
            if (!$resourceId && !empty($resourceNames)) {
                $title .= ' [' . implode(', ', $resourceNames) . ']';
            }

            $rStart = $r->getStartDate();
            $rEnd = $r->getEndDate();
            if ($rStart instanceof \DateTimeImmutable) { $rStart = \DateTime::createFromImmutable($rStart); }
            if ($rEnd instanceof \DateTimeImmutable) { $rEnd = \DateTime::createFromImmutable($rEnd); }
            $fc = new FcEvent($title, $rStart, $rEnd);

            // Options JS FullCalendar pass-through
            $options = [
                // CORRECTION : Ton JS attend un UUID pour la redirection !
                'id'    => $series && method_exists($series, 'getUuid') ? $series->getUuid() : $r->getId(),
                'classNames' => ['booking-instance'],
            ];

            // Si la ressource a une couleur (ex: #ff0000), on l'applique à l'événement
            if ($color) {
                $options['backgroundColor'] = $color;
                $options['borderColor'] = $color;
            }

            $fc->setOptions($options);
            $event->addEvent($fc);
        }

        // ----- 2) TimeBlocks en 'background' -----
        // Si une ressource est ciblée, on va chercher son layout via schedule
        if ($resourceId) {
            $resource = $this->resources->find($resourceId);
            if ($resource && $resource->getSchedule() && $resource->getSchedule()->getLayout()) {
                $layout = $resource->getSchedule()->getLayout();
                $blocks = $this->timeBlocks->findBy(['layout' => $layout]);

                // Réplique chaque bloc pour chaque jour de l’intervalle (hebdo)
                $tz = new \DateTimeZone('Europe/Paris');
                $period = new \DatePeriod(
                    (new \DateTimeImmutable($start->format('Y-m-d'), $tz)),
                    new \DateInterval('P1D'),
                    (new \DateTimeImmutable($end->format('Y-m-d'), $tz))
                );

                foreach ($period as $day) {
                    foreach ($blocks as $b) {
                        $startDt = $day
                            ->setTime((int)$b->getStartTime()->format('H'), (int)$b->getStartTime()->format('i'));
                        $endDt = $day
                            ->setTime((int)$b->getEndTime()->format('H'), (int)$b->getEndTime()->format('i'));
                        // FullCalendar background event
                        $bg = new FcEvent($b->getLabel() ?? 'Ouvert', \DateTime::createFromImmutable($startDt), \DateTime::createFromImmutable($endDt));
                        $bg->setOptions([
                            'display' => 'background',
                            'overlap' => true,
                            'classNames' => ['open-block'],
                        ]);
                        $event->addEvent($bg);
                    }
                }
            }
        }
    }
}
