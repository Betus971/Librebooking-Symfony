<?php
namespace App\Service;

use App\Entity\Resource;
use App\Entity\ReservationStatus;
use App\Entity\TimeBlock;
use Doctrine\ORM\EntityManagerInterface;

class AvailabilityChecker
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    /**
     * Retourne true si aucun chevauchement d’instances pour la ressource et l’intervalle [start,end)
     */


    public function isFree(Resource $resource, \DateTimeInterface $start, \DateTimeInterface $end): bool
    {
        // 1) Conflits existants : on considère comme busy les résa "actives"
        //    (PENDING + APPROVED). REJECTED/CANCELLED ne bloquent rien.
        $qb = $this->entityManager->createQueryBuilder();
        $conflictCount = (int) $qb->select('COUNT(i.id)')
            ->from(\App\Entity\ReservationInstance::class, 'i')
            ->join(\App\Entity\ReservationResource::class, 'rr', 'WITH', 'rr.series = i.series')
            ->join('i.series', 's')
            ->where('rr.resource = :res')
            ->andWhere('i.startDate < :end')
            ->andWhere('i.endDate > :start')
            ->andWhere('IDENTITY(s.status) IN (:activeStatuses)')
            ->setParameter('res', $resource)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('activeStatuses', ReservationStatus::ACTIVE_STATUSES)
            ->getQuery()
            ->getSingleScalarResult();

        if ($conflictCount > 0) {
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
        $rows = $this->entityManager->createQueryBuilder()
            ->select('tb.dayOfWeek AS dow, tb.startTime AS s, tb.endTime AS e')
            ->from(\App\Entity\TimeBlock::class, 'tb')
            ->where('tb.layout = :layout')
            ->andWhere('tb.availabilityCode = :open')
            ->setParameter('open', TimeBlock::OPEN)
            ->setParameter('layout', $layout)
            ->getQuery()
            ->getArrayResult();

        // 4) Indexer par jour
        $openByDay = [];
        foreach ($rows as $r) {
            $key = ($r['dow'] === null) ? 'ALL' : (int) $r['dow'];
            $openByDay[$key][] = [
                ($r['s'] instanceof \DateTimeInterface) ? $r['s']->format('H:i:s') : (string) $r['s'],
                ($r['e'] instanceof \DateTimeInterface) ? $r['e']->format('H:i:s') : (string) $r['e'],
            ];
        }

        // 5) Boucle jour par jour
        $cursor = (clone $start)->setTime(0, 0, 0);
        $lastDay = (clone $end)->setTime(0, 0, 0);

        while ($cursor <= $lastDay) {
            $dayStartDT = (clone $cursor);
            if ($dayStartDT < $start) { $dayStartDT = clone $start; }

            $endOfDay = (clone $cursor)->setTime(23, 59, 59);
            $dayEndDT = ($end < $endOfDay) ? clone $end : $endOfDay;

            if ($dayStartDT >= $dayEndDT) {
                $cursor = (clone $cursor)->modify('+1 day')->setTime(0,0,0);
                continue;
            }

            $dow = (int) $cursor->format('w');
            $needStart = $dayStartDT->format('H:i:s');
            $needEnd   = $dayEndDT->format('H:i:s');

            // Récupérer les blocs bruts
            $blocks = ($openByDay['ALL'] ?? []);
            if (!empty($openByDay[$dow])) {
                $blocks = array_merge($blocks, $openByDay[$dow]);
            }

            // --- DEBUT MODIFICATION : Fusionner les blocs contigus ---

            // A. Trier par heure de début
            usort($blocks, fn($a, $b) => strcmp($a[0], $b[0]));

            // B. Fusionner (Merge)
            $mergedBlocks = [];
            foreach ($blocks as $b) {
                if (empty($mergedBlocks)) {
                    $mergedBlocks[] = $b;
                    continue;
                }

                // Référence au dernier bloc ajouté
                $lastIndex = count($mergedBlocks) - 1;
                $last = $mergedBlocks[$lastIndex];

                // Si le bloc actuel commence avant (ou pile quand) le dernier finit
                if ($b[0] <= $last[1]) {
                    // On prolonge la fin si nécessaire (ex: 08-09 + 09-10 => 08-10)
                    if ($b[1] > $last[1]) {
                        $mergedBlocks[$lastIndex][1] = $b[1];
                    }
                } else {
                    // Sinon c'est un nouveau bloc séparé (il y a un trou)
                    $mergedBlocks[] = $b;
                }
            }
            // ---------------------------------------------------------

            // Vérifier si notre demande rentre dans l'un des "grands blocs fusionnés"
            $covered = false;
            foreach ($mergedBlocks as [$bStart, $bEnd]) {
                if ($needStart >= $bStart && $needEnd <= $bEnd) {
                    $covered = true;
                    break;
                }
            }

            if (!$covered) {
                return false;
            }

            $cursor = (clone $cursor)->modify('+1 day')->setTime(0, 0, 0);
        }

        return true;
    }

}
