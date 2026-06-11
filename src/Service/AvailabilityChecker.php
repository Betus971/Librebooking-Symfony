<?php
namespace App\Service;

use App\Entity\Resource;
use App\Repository\ReservationInstanceRepository;
use App\Repository\TimeBlockRepository;

class AvailabilityChecker
{
    public function __construct(
        private ReservationInstanceRepository $instances,
        private TimeBlockRepository $timeBlocks,
    ) {}

    /**
     * Retourne true si aucun chevauchement d’instances pour la ressource et l’intervalle [start,end)
     */
    public function isFree(Resource $resource, \DateTimeInterface $start, \DateTimeInterface $end): bool
    {
        // 1) Conflits existants : on considère comme busy les résa "actives"
        //    (PENDING + APPROVED). REJECTED/CANCELLED ne bloquent rien.
        if ($this->instances->hasOverlapForResource($resource, $start, $end)) {
            return false;
        }

        // 2) Layout de la ressource
        $schedule = $resource->getSchedule();
        if (!$schedule) return false;

        // 2b) Vérification de la plage de validité du planning (startDate / endDate)
        $scheduleStart = $schedule->getStartDate();
        $scheduleEnd   = $schedule->getEndDate();

        if ($scheduleStart !== null && $start < $scheduleStart) {
            return false; // Réservation avant l'ouverture du planning
        }
        if ($scheduleEnd !== null && $end > $scheduleEnd->modify('+1 day')) {
            return false; // Réservation après la fermeture du planning
        }

        $layout = $schedule->getLayout();
        if (!$layout) return false;

        // 3) Précharger les créneaux ouverts
        $rows = $this->timeBlocks->findOpenBlocksByLayout($layout);

        // 4) Indexer par jour
        $openByDay = [];
        foreach ($rows as $r) {
            $key = ($r['dow'] === null) ? 'ALL' : (int) $r['dow'];
            $openByDay[$key][] = [
                ($r['s'] instanceof \DateTimeInterface) ? $r['s']->format('H:i:s') : (string) $r['s'],
                ($r['e'] instanceof \DateTimeInterface) ? $r['e']->format('H:i:s') : (string) $r['e'],
            ];
        }

        // 5) Vérification des horaires d'ouverture
        $isMultiDay = $start->format('Y-m-d') !== $end->format('Y-m-d');

        if ($isMultiDay) {
            // Résa multi-jours : on vérifie uniquement que l'heure de début tombe dans un
            // créneau ouvert du premier jour, et que l'heure de fin tombe dans un créneau
            // ouvert du dernier jour. Les jours intermédiaires sont implicitement bloqués
            // par la réservation elle-même (pas de contrainte horaire supplémentaire).
            $startTime = $start->format('H:i:s');
            if (!$this->fitsInOpenWindows($startTime, $startTime, $openByDay, (int) $start->format('w'))) {
                return false;
            }

            $endTime = $end->format('H:i:s');
            if (!$this->fitsInOpenWindows($endTime, $endTime, $openByDay, (int) $end->format('w'))) {
                return false;
            }
        } else {
            // Résa single-day : la plage complète doit rentrer dans un créneau ouvert
            $dow = (int) $start->format('w');
            if (!$this->fitsInOpenWindows($start->format('H:i:s'), $end->format('H:i:s'), $openByDay, $dow)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Vérifie que l'intervalle [needStart, needEnd] est couvert par les blocs ouverts
     * fusionnés du jour donné. Les blocs 'ALL' (dow=null) s'appliquent à tous les jours.
     * Les fins de blocs à '00:00:00' (minuit) sont normalisées en '24:00:00'.
     */
    private function fitsInOpenWindows(string $needStart, string $needEnd, array $openByDay, int $dow): bool
    {
        $blocks = array_merge($openByDay['ALL'] ?? [], $openByDay[$dow] ?? []);
        // Normaliser 00:00:00 → 24:00:00 (minuit stocké comme début de journée suivante)
        $blocks = array_map(fn($b) => [$b[0], $b[1] === '00:00:00' ? '24:00:00' : $b[1]], $blocks);
        // Trier puis fusionner les blocs contigus / chevauchants
        usort($blocks, fn($a, $b) => strcmp($a[0], $b[0]));
        $merged = [];
        foreach ($blocks as $b) {
            if (empty($merged)) { $merged[] = $b; continue; }
            $last = count($merged) - 1;
            if ($b[0] <= $merged[$last][1]) {
                if ($b[1] > $merged[$last][1]) $merged[$last][1] = $b[1];
            } else {
                $merged[] = $b;
            }
        }
        foreach ($merged as [$bStart, $bEnd]) {
            if ($needStart >= $bStart && $needEnd <= $bEnd) return true;
        }
        return false;
    }

}
