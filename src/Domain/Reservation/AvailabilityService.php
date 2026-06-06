<?php

namespace App\Domain\Reservation;

use App\Domain\Reservation\Checker\ResourceRulesChecker;
use App\Entity\Resource;
use App\Entity\TimeBlock;
use App\Repository\BlackoutInstanceRepository;
use App\Repository\ReservationInstanceRepository;

class AvailabilityService
{
    public function __construct(
        private ReservationInstanceRepository $instances,
        private BlackoutInstanceRepository $blackouts,
        private ResourceRulesChecker $rules,
    ) {}

    /** Indexe les périodes occupées (réservations + blackouts) par ressource sur l'intervalle donné. */
    public function busyIndex(array $resources, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $ids = array_map(fn($r) => $r->getId(), $resources);

        $rows1 = $this->instances->busyIntervalsForResourcesBetween($ids, $start, $end);
        $rows2 = $this->blackouts?->busyIntervalsForResourcesBetween($ids, $start, $end) ?? []; // si pas d’entité → tableau vide

        $busy = [];
        foreach ([$rows1,$rows2] as $rows) {
            foreach ($rows as $row) {
                $busy[$row['rid']][] = [$row['s'], $row['e']];
            }
        }
        return $busy;
    }

    /**
     * Renvoie des fenêtres libres (tableau d'objets {start,end}) pour un jour.
     *
     * Les fenêtres retournées sont :
     *   - dans les TimeBlocks ouverts du jour
     *   - hors des périodes busy (résa actives + blackouts)
     *   - alignées sur min_increment, de durée min_duration
     *   - validées par {@see ResourceRulesChecker} (préavis min/max, etc.)
     *     → ainsi un créneau affiché est TOUJOURS effectivement réservable.
     *
     * @param \DateTimeInterface|null $now  Injectable pour les tests ; sinon « maintenant ».
     */
    public function freeWindowsForDay(Resource $r, \DateTimeInterface $day, array $busyIndex, ?\DateTimeInterface $now = null): array
    {
        $layout = $r->getSchedule()->getLayout();
        // 1. On récupère le numéro du jour (0=Dimanche, 1=Lundi, etc.)
        $currentDow = (int) $day->format('w');

        $openBlocks = array_filter(
            $layout->getTimeBlocks()->toArray(),
            fn($b) => $b->isOpen()
                && ($b->getDayOfWeek() === null || $b->getDayOfWeek() === $currentDow)
        );

        // Fenêtres ouvertes de la journée
        $open = [];
        foreach ($openBlocks as $b) {
            $s = (clone $day)->setTime(...$this->hms($b->getStartTime()));
            $e = (clone $day)->setTime(...$this->hms($b->getEndTime()));
            $open[] = [$s, $e];
        }

        // Soustraire périodes occupées (réservations + blackouts)
        $busy = $busyIndex[$r->getId()] ?? [];
        $free = $this->subtractMany($open, $busy);

        // Découper selon min_increment et min_duration
        $increment = max(15, (int) $r->getMinIncrement() ?: 30); // minutes
        $minDur = max($increment, (int) $r->getMinDuration() ?: $increment);

        // « Now » utilisé par le checker pour évaluer le préavis min/max.
        $now ??= new \DateTimeImmutable('now', $day->getTimezone() ?: new \DateTimeZone(date_default_timezone_get()));

        $windows = [];
        foreach ($free as [$s, $e]) {
            for ($t = clone $s; $t < $e; $t = (clone $t)->modify("+{$increment} minutes")) {
                $end = (clone $t)->modify("+{$minDur} minutes");
                if ($end > $e) {
                    continue;
                }

                // Filtre final : on ne montre que les créneaux qui passent les règles métier
                // (préavis min/max, durée, multi-jour, alignement). Évite d'afficher un
                // créneau sur lequel l'utilisateur se prendrait une erreur au clic.
                if ([] !== $this->rules->check($r, $t, $end, $now)) {
                    continue;
                }

                $windows[] = (object)['start' => $t, 'end' => $end];
            }
        }
        return $windows;
    }

    private function subtractMany(array $opens, array $busys): array
    {
        foreach ($busys as [$bs,$be]) {
            $next = [];
            foreach ($opens as [$os,$oe]) {
                // pas d'overlap
                if ($be <= $os || $bs >= $oe) { $next[] = [$os,$oe]; continue; }
                // left piece
                if ($bs > $os) { $next[] = [$os, $bs]; }
                // right piece
                if ($be < $oe) { $next[] = [$be, $oe]; }
            }
            $opens = $next;
        }
        return $opens;
    }

    private function hms(\DateTimeInterface $t): array { return [(int)$t->format('H'), (int)$t->format('i'), 0]; }

}
