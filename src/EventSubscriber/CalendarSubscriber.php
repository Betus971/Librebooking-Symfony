<?php
// src/EventSubscriber/CalendarSubscriber.php

namespace App\EventSubscriber;

use App\Repository\BlackoutInstanceRepository;
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
        private readonly BlackoutInstanceRepository $blackouts,
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
        $categoryId = isset($filterArray['categoryId']) && $filterArray['categoryId'] !== '' ? (int)$filterArray['categoryId'] : null;

        // ----- 1) Réservations (instances) -----
        if ($resourceId) {
            $reservations = $this->instances->findOverlappingForResource($resourceId, $start, $end);
        } elseif ($categoryId) {
            $reservations = $this->instances->findOverlappingForCategory($categoryId, $start, $end);
        } else {
            $reservations = $this->instances->findOverlappingAllResources($start, $end);
        }

        // Pré-calculer les bornes d'ouverture par layout pour couper proprement les événements sur plusieurs jours
        $layoutBounds = [];
        $allBlocks = $this->timeBlocks->findAll();
        foreach ($allBlocks as $b) {
            if (!$b->isOpen()) continue;
            $lId = $b->getLayout()->getId();
            $dow = $b->getDayOfWeek();
            
            $bStart = $b->getStartTime()->format('H:i:s');
            $bEnd = $b->getEndTime()->format('H:i:s');

            if (!isset($layoutBounds[$lId])) {
                $layoutBounds[$lId] = [];
            }

            if ($dow === null) {
                for ($i = 0; $i <= 6; $i++) {
                    if (!isset($layoutBounds[$lId][$i])) {
                        $layoutBounds[$lId][$i] = ['start' => $bStart, 'end' => $bEnd];
                    } else {
                        $layoutBounds[$lId][$i]['start'] = min($layoutBounds[$lId][$i]['start'], $bStart);
                        $layoutBounds[$lId][$i]['end'] = max($layoutBounds[$lId][$i]['end'], $bEnd);
                    }
                }
            } else {
                if (!isset($layoutBounds[$lId][$dow])) {
                    $layoutBounds[$lId][$dow] = ['start' => $bStart, 'end' => $bEnd];
                } else {
                    $layoutBounds[$lId][$dow]['start'] = min($layoutBounds[$lId][$dow]['start'], $bStart);
                    $layoutBounds[$lId][$dow]['end'] = max($layoutBounds[$lId][$dow]['end'], $bEnd);
                }
            }
        }

        foreach ($reservations as $r) {
            $series = method_exists($r, 'getSeries') ? $r->getSeries() : null;
            $title = $series && $series->getTitle() ? $series->getTitle() : ($r->getReferenceNumber() ?? 'Réservation');

            // --- NOUVEAUTÉ : On cherche les ressources liées et leur couleur ---
            $resourceNames = [];
            $color = null;

            // Palette « couleurs illustratives » du DSFR (choix UX) pour les
            // ressources sans couleur définie en base. Attribution STABLE par
            // ID (une ressource garde toujours la même couleur).
            $dsfrPalette = [
                '#6E445A', // Glycine
                '#E18B76', // Macaron
                '#009081', // Émeraude
                '#37635F', // Menthe
                '#C3992A', // Tournesol
                '#8D533E', // Café Crème
                '#5B3A6E', // Aubergine
                '#66673D', // Tilleul Verveine
                '#A94645', // Framboise
                '#666666', // Gris Ardoise
            ];

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

            // Couleur du texte auto (noir/blanc) selon la luminance du fond,
            // pour rester lisible sur les teintes claires (Macaron, Tournesol…).
            $textColor = $color ? self::contrastText($color) : null;

            // Si on est sur "Toutes les ressources", on ajoute les noms des salles au titre !
            if (!$resourceId && !empty($resourceNames)) {
                $title .= ' [' . implode(', ', $resourceNames) . ']';
            }

            $rStart = clone $r->getStartDate();
            $rEnd = clone $r->getEndDate();
            if ($rStart instanceof \DateTimeImmutable) { $rStart = \DateTime::createFromImmutable($rStart); }
            if ($rEnd instanceof \DateTimeImmutable) { $rEnd = \DateTime::createFromImmutable($rEnd); }

            $isMultiDay = $rStart->format('Y-m-d') !== $rEnd->format('Y-m-d');

            // Recherche du Layout ID pour obtenir les horaires d'ouverture de cette ressource
            $layoutIdForEvent = null;
            if ($series && method_exists($series, 'getReservationResources')) {
                foreach ($series->getReservationResources() as $rr) {
                    $res = $rr->getResource();
                    if ($res && $res->getSchedule() && $res->getSchedule()->getLayout()) {
                        $layoutIdForEvent = $res->getSchedule()->getLayout()->getId();
                        break;
                    }
                }
            }

            // Génération de l'événement (ou des sous-événements)
            if ($isMultiDay) {
                $period = new \DatePeriod(
                    (new \DateTimeImmutable($rStart->format('Y-m-d'))),
                    new \DateInterval('P1D'),
                    (new \DateTimeImmutable($rEnd->format('Y-m-d')))->modify('+1 day')
                );

                foreach ($period as $day) {
                    $currentDayStr = $day->format('Y-m-d');
                    $dow = (int)$day->format('w'); // 0 = Dimanche
                    
                    // Horaires par défaut si la salle n'a pas de layout
                    $openTime = '07:00:00';
                    $closeTime = '18:00:00';

                    if ($layoutIdForEvent && isset($layoutBounds[$layoutIdForEvent][$dow])) {
                        $openTime = $layoutBounds[$layoutIdForEvent][$dow]['start'];
                        $closeTime = $layoutBounds[$layoutIdForEvent][$dow]['end'];
                    }

                    $chunkStart = new \DateTime($currentDayStr . ' ' . $openTime);
                    $chunkEnd = new \DateTime($currentDayStr . ' ' . $closeTime);

                    if ($currentDayStr === $rStart->format('Y-m-d')) {
                        $chunkStart = max($chunkStart, clone $rStart);
                    }
                    if ($currentDayStr === $rEnd->format('Y-m-d')) {
                        $chunkEnd = min($chunkEnd, clone $rEnd);
                    }

                    if ($chunkStart < $chunkEnd) {
                        $fc = new FcEvent($title, $chunkStart, $chunkEnd);
                        $options = [
                            'id'    => $series && method_exists($series, 'getUuid') ? $series->getUuid() : $r->getId(),
                            'classNames' => ['booking-instance', 'multi-day-chunk'],
                        ];
                        if ($color) {
                            $options['backgroundColor'] = $color;
                            $options['borderColor'] = $color;
                            $options['textColor'] = $textColor;
                        }
                        $fc->setOptions($options);
                        $event->addEvent($fc);
                    }
                }
            } else {
                $fc = new FcEvent($title, $rStart, $rEnd);
                $options = [
                    'id'    => $series && method_exists($series, 'getUuid') ? $series->getUuid() : $r->getId(),
                    'classNames' => ['booking-instance'],
                ];
                if ($color) {
                    $options['backgroundColor'] = $color;
                    $options['borderColor'] = $color;
                    $options['textColor'] = $textColor;
                }
                $fc->setOptions($options);
                $event->addEvent($fc);
            }
        }

        // ----- 2) FERMETURES (blackouts) de la ressource -----
        // On n'affiche PLUS les créneaux "Ouvert" en vert (illisible et inutile) :
        // un créneau ouvert = simple case vide. En revanche, on affiche les
        // FERMETURES en "Bloqué" (comme l'agenda Presta), pour voir d'un coup
        // d'œil quand la ressource est indisponible (fermeture de telle date à
        // telle date).
        if ($resourceId) {
            foreach ($this->blackouts->busyIntervalsForResourcesBetween([$resourceId], $start, $end) as $bo) {
                $s = $bo['s'] instanceof \DateTimeInterface ? $bo['s'] : new \DateTime((string) $bo['s']);
                $e = $bo['e'] instanceof \DateTimeInterface ? $bo['e'] : new \DateTime((string) $bo['e']);
                $label = 'Bloqué' . (!empty($bo['title']) ? ' — ' . $bo['title'] : '');

                $fc = new FcEvent($label, $s, $e);
                $fc->setOptions([
                    'backgroundColor' => '#8a8a8a',
                    'borderColor'     => '#6a6a6a',
                    'textColor'       => '#ffffff',
                    'classNames'      => ['blackout-block'],
                ]);
                $event->addEvent($fc);
            }
        }
    }

    /**
     * Retourne #161616 (texte foncé) ou #ffffff (texte clair) selon la luminance
     * perçue de la couleur de fond (hex #RRGGBB), pour garder un texte lisible
     * sur les teintes claires (Macaron, Tournesol…) comme foncées.
     */
    private static function contrastText(string $hex): string
    {
        $h = ltrim($hex, '#');
        if (strlen($h) === 3) {
            $h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
        }
        if (strlen($h) !== 6 || !ctype_xdigit($h)) {
            return '#ffffff';
        }
        $r = hexdec(substr($h, 0, 2));
        $g = hexdec(substr($h, 2, 2));
        $b = hexdec(substr($h, 4, 2));
        // Luminance perçue (YIQ) : seuil ~150 → texte foncé au-dessus.
        $yiq = ($r * 299 + $g * 587 + $b * 114) / 1000;

        return $yiq >= 150 ? '#161616' : '#ffffff';
    }
}
