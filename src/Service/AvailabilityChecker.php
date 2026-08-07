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
        //    Requête encapsulée dans le repository (RF-3).
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

        // 3) Précharger les créneaux ouverts (requête encapsulée — RF-3)
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
        //
        // Deux cas distincts :
        //
        //  A. RÉSERVATION SUR UN SEUL JOUR (start et end sur la même date) :
        //     la plage [start_time, end_time] doit rentrer ENTIÈREMENT dans
        //     les créneaux ouverts du jour (fusionnés).
        //
        //  B. RÉSERVATION MULTI-JOURS (sur plusieurs dates) :
        //     la sémantique métier est que la résa OCCUPE la salle de bout en
        //     bout sur toute la période, même en dehors des heures d'ouverture
        //     (ex. nuit, week-end). On vérifie donc uniquement que :
        //       - l'heure de DÉBUT est dans un créneau ouvert du JOUR DE DÉBUT
        //       - l'heure de FIN   est dans un créneau ouvert du JOUR DE FIN
        //     Les jours intermédiaires sont implicitement « bloqués » par la
        //     résa elle-même — pas besoin qu'ils aient un TimeBlock ouvert.
        //
        // ⚠️  Bug historique : avant ce correctif, la boucle jour par jour
        //     comparait la portion [09:00, 23:59:59] du 1er jour multi-jours
        //     à un TimeBlock 08:00–18:00 → toujours faux. Les résas
        //     multi-jours étaient impossibles à valider même si
        //     `allowMultiday` était coché côté ressource.

        $isSingleDay = $start->format('Y-m-d') === $end->format('Y-m-d');

        if ($isSingleDay) {
            // ── Cas A : un seul jour ──────────────────────────────────────
            if (!$this->fitsInOpenWindows($start, $end, $openByDay)) {
                return false;
            }
        } else {
            // ── Cas B : multi-jours ───────────────────────────────────────
            // 1) Le moment de DÉBUT doit être pile dans un créneau ouvert
            //    du jour de début. On simule une mini-plage [start, start+1s]
            //    qui ne dépasse pas le créneau d'ouverture.
            $startProbeEnd = (clone $start)->modify('+1 second');
            if (!$this->fitsInOpenWindows($start, $startProbeEnd, $openByDay)) {
                return false;
            }

            // 2) Le moment de FIN doit être pile dans un créneau ouvert
            //    du jour de fin.
            $endProbeStart = (clone $end)->modify('-1 second');
            if (!$this->fitsInOpenWindows($endProbeStart, $end, $openByDay)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Vérifie si la plage [start, end) — supposée tenir sur UN SEUL jour
     * calendaire — rentre entièrement dans un ou plusieurs créneaux ouverts
     * fusionnés du jour correspondant.
     *
     * @param array<int|string, list<array{0:string,1:string}>> $openByDay
     */
    private function fitsInOpenWindows(
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        array $openByDay
    ): bool {
        $dow       = (int) $start->format('w');
        $needStart = $start->format('H:i:s');
        $needEnd   = $end->format('H:i:s');

        // Blocs ouverts qui s'appliquent à ce jour (spécifiques + 'ALL')
        $blocks = ($openByDay['ALL'] ?? []);
        if (!empty($openByDay[$dow])) {
            $blocks = array_merge($blocks, $openByDay[$dow]);
        }
        if (empty($blocks)) {
            return false;
        }

        // Trier par heure de début, puis fusionner les contigus
        usort($blocks, fn($a, $b) => strcmp($a[0], $b[0]));
        $merged = [];
        foreach ($blocks as $b) {
            if (empty($merged)) {
                $merged[] = $b;
                continue;
            }
            $lastIdx = count($merged) - 1;
            if ($b[0] <= $merged[$lastIdx][1]) {
                if ($b[1] > $merged[$lastIdx][1]) {
                    $merged[$lastIdx][1] = $b[1];
                }
            } else {
                $merged[] = $b;
            }
        }

        // Tenir entièrement dans un bloc fusionné
        foreach ($merged as [$bStart, $bEnd]) {
            if ($needStart >= $bStart && $needEnd <= $bEnd) {
                return true;
            }
        }
        return false;
    }

}
