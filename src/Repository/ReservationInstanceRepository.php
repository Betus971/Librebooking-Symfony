<?php

namespace App\Repository;

use App\Entity\ReservationInstance;
use App\Entity\ReservationSeries;
use App\Entity\ReservationStatus;
use App\Entity\Resource;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReservationInstance>
 */
class ReservationInstanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReservationInstance::class);
    }

    /**
     * Retourne les instances de réservation actives (PENDING|APPROVED) qui
     * chevauchent la plage et concernent au moins une ressource du schedule.
     *
     * Les statuts REJECTED/CANCELLED sont exclus (la résa ne bloque plus rien).
     */
    public function findInRangeBySchedule(\DateTimeInterface $start, \DateTimeInterface $end, int $scheduleId): array
    {
        return $this->createQueryBuilder('i')
            ->join('i.series', 's')
            ->join('App\Entity\ReservationResource', 'rr', 'WITH', 'rr.series = s')
            ->join('rr.resource', 'r')
            ->andWhere('i.startDate < :end AND i.endDate > :start')
            ->andWhere('r.schedule = :scheduleId')
            ->andWhere('IDENTITY(s.status) IN (:activeStatuses)')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('scheduleId', $scheduleId)
            ->setParameter('activeStatuses', ReservationStatus::ACTIVE_STATUSES)
            ->getQuery()->getResult();
    }

    /**
     * Idem {@see findInRangeBySchedule} mais filtré sur une ressource précise.
     */
    public function findInRangeByResource(
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        int $resourceId
    ): array {
        return $this->createQueryBuilder('i')
            ->join('i.series', 's')
            ->join('App\Entity\ReservationResource', 'rr', 'WITH', 'rr.series = s')
            ->join('rr.resource', 'r')
            ->andWhere('i.startDate < :end AND i.endDate > :start')
            ->andWhere('r.id = :resourceId')
            ->andWhere('IDENTITY(s.status) IN (:activeStatuses)')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('resourceId', $resourceId)
            ->setParameter('activeStatuses', ReservationStatus::ACTIVE_STATUSES)
            ->getQuery()->getResult();
    }

    /**
     * Instances actives qui chevauchent la plage pour une ressource donnée.
     * Utilisé par le calendrier et les détections de conflit.
     */
    public function findOverlappingForResource(int $resourceId, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('ri')
            ->innerJoin('ri.series', 'rs')
            ->innerJoin('rs.reservationResources', 'rr')
            ->andWhere('ri.startDate < :end')
            ->andWhere('ri.endDate   > :start')
            ->andWhere('rr.resource = :rid')
            ->andWhere('IDENTITY(rs.status) IN (:activeStatuses)')
            ->setParameter('start', $start)
            ->setParameter('end',   $end)
            ->setParameter('rid',   $resourceId)
            ->setParameter('activeStatuses', ReservationStatus::ACTIVE_STATUSES)
            ->getQuery()->getResult();
    }

    /**
     * Instances actives qui chevauchent la plage, toutes ressources confondues.
     */
    public function findOverlappingAllResources(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('ri')
            ->innerJoin('ri.series', 'rs')
            ->andWhere('ri.startDate < :end')
            ->andWhere('ri.endDate   > :start')
            ->andWhere('IDENTITY(rs.status) IN (:activeStatuses)')
            ->setParameter('start', $start)
            ->setParameter('end',   $end)
            ->setParameter('activeStatuses', ReservationStatus::ACTIVE_STATUSES)
            ->getQuery()->getResult();
    }

    /**
     * Instances actives qui chevauchent la plage, filtrées par catégorie de ressource.
     */
    public function findOverlappingForCategory(int $categoryId, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('ri')
            ->innerJoin('ri.series', 'rs')
            ->innerJoin('rs.reservationResources', 'rr')
            ->innerJoin('rr.resource', 'res')
            ->andWhere('ri.startDate < :end')
            ->andWhere('ri.endDate   > :start')
            ->andWhere('res.category = :cid')
            ->andWhere('IDENTITY(rs.status) IN (:activeStatuses)')
            ->setParameter('start', $start)
            ->setParameter('end',   $end)
            ->setParameter('cid',   $categoryId)
            ->setParameter('activeStatuses', ReservationStatus::ACTIVE_STATUSES)
            ->getQuery()->getResult();
    }

    /**
     * Indexe les intervalles occupés (résa actives uniquement) pour une liste
     * de ressources, sur la plage [start, end]. Retourne un tableau brut :
     *   [ ['rid' => int, 's' => DateTime, 'e' => DateTime], ... ]
     *
     * Utilisé par AvailabilityService pour construire les créneaux libres.
     */
    public function busyIntervalsForResourcesBetween(array $resourceIds, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        // Court-circuit : pas de ressources → pas de busy, évite une requête SQL inutile
        // (et certaines bases n'apprécient pas IN (:empty)).
        if (empty($resourceIds)) {
            return [];
        }

        return $this->createQueryBuilder('i')
            ->select('r.id AS rid, i.startDate AS s, i.endDate AS e')
            ->join('i.series', 'sr')
            ->join('App\Entity\ReservationResource', 'rr', 'WITH', 'rr.series = sr')
            ->join('rr.resource', 'r')
            ->andWhere('r.id IN (:ids)')
            ->andWhere('i.startDate < :end')
            ->andWhere('i.endDate > :start')
            ->andWhere('IDENTITY(sr.status) IN (:activeStatuses)')
            ->setParameter('ids', $resourceIds)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('activeStatuses', ReservationStatus::ACTIVE_STATUSES)
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Vrai s'il existe au moins une instance ACTIVE (PENDING|APPROVED) qui
     * chevauche [start, end) pour la ressource donnée.
     *
     * Encapsule la requête de détection de conflit historiquement portée par
     * {@see \App\Service\AvailabilityChecker::isFree()} (RF-3).
     */
    public function hasOverlapForResource(Resource $resource, \DateTimeInterface $start, \DateTimeInterface $end): bool
    {
        $count = (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->join('App\Entity\ReservationResource', 'rr', 'WITH', 'rr.series = i.series')
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

        return $count > 0;
    }

    /**
     * Vrai s'il existe au moins une instance de la série dont la date de début
     * est strictement postérieure à $since (réservation « à venir »).
     *
     * Encapsule la requête historiquement portée par
     * {@see \App\Domain\Reservation\ReservationWorkflow::ensureAllowed()} (RF-4).
     */
    public function hasUpcoming(ReservationSeries $series, \DateTimeInterface $since): bool
    {
        $count = (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->where('i.series = :s')
            ->andWhere('i.startDate > :now')
            ->setParameter('s', $series)
            ->setParameter('now', $since)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Statistiques d'usage PAR RESSOURCE, sur les réservations ACTIVES
     * (PENDING + APPROVED) : nombre de réservations et cumul d'heures réservées.
     *
     * Respecte le SCOPE DE VISIBILITÉ HYBRIDE : si $scope['scoped'] est vrai
     * (gestionnaire non super-admin), on ne retourne que les ressources
     * appartenant à un de ses groupes OU portant son code unité. Sans groupe
     * ni code unité connu → aucun résultat (sécurité par défaut).
     *
     * L'agrégation (comptage + somme des durées) est faite en PHP pour rester
     * portable (le calcul de différence de dates en DQL est spécifique au SGBD).
     *
     * @param array{scoped?: bool, resourceGroupIds?: int[], scopeCodeUnite?: ?int} $scope
     * @return list<array{id: int, name: string, count: int, hours: float}>
     *         trié par nombre de réservations décroissant.
     */
    public function resourceUsageStats(
        array $scope,
        ?\DateTimeInterface $start = null,
        ?\DateTimeInterface $end = null,
        ?int $categoryId = null,
    ): array {
        $qb = $this->createQueryBuilder('i')
            ->select('r.id AS rid', 'r.name AS rname', 'i.startDate AS startDate', 'i.endDate AS endDate')
            ->join('i.series', 's')
            ->join('App\Entity\ReservationResource', 'rr', 'WITH', 'rr.series = s')
            ->join('rr.resource', 'r')
            ->where('IDENTITY(s.status) IN (:active)')
            ->setParameter('active', ReservationStatus::ACTIVE_STATUSES);

        // Filtre période : réservations commençant dans l'intervalle demandé.
        if (null !== $start) {
            $qb->andWhere('i.startDate >= :periodStart')->setParameter('periodStart', $start);
        }
        if (null !== $end) {
            $qb->andWhere('i.startDate <= :periodEnd')->setParameter('periodEnd', $end);
        }

        // Filtre catégorie de ressource.
        if (null !== $categoryId) {
            $qb->andWhere('IDENTITY(r.category) = :catId')->setParameter('catId', $categoryId);
        }

        // Scope hybride (mêmes règles que ReservationSeriesRepository::applyHybridScope).
        if (!empty($scope['scoped'])) {
            $groupIds = (isset($scope['resourceGroupIds']) && is_array($scope['resourceGroupIds']))
                ? $scope['resourceGroupIds'] : [];
            $unite = $scope['scopeCodeUnite'] ?? null;

            $conds = [];
            if (!empty($groupIds)) {
                $conds[] = 'IDENTITY(r.resourceGroup) IN (:scopeGroupIds)';
            }
            if (null !== $unite) {
                $conds[] = 'r.codeUnite = :scopeUnite';
            }

            if ([] === $conds) {
                $qb->andWhere('1 = 0'); // ni groupe ni unité → rien.
            } else {
                $qb->andWhere('(' . implode(' OR ', $conds) . ')');
                if (!empty($groupIds)) {
                    $qb->setParameter('scopeGroupIds', $groupIds);
                }
                if (null !== $unite) {
                    $qb->setParameter('scopeUnite', $unite);
                }
            }
        }

        $rows = $qb->getQuery()->getArrayResult();

        $byResource = [];
        foreach ($rows as $row) {
            $rid = (int) $row['rid'];
            if (!isset($byResource[$rid])) {
                $byResource[$rid] = ['id' => $rid, 'name' => (string) $row['rname'], 'count' => 0, 'hours' => 0.0];
            }
            $byResource[$rid]['count']++;

            $start = $row['startDate'];
            $end   = $row['endDate'];
            if ($start instanceof \DateTimeInterface && $end instanceof \DateTimeInterface) {
                $byResource[$rid]['hours'] += max(0, $end->getTimestamp() - $start->getTimestamp()) / 3600;
            }
        }

        // Arrondi des heures + tri par nombre de réservations décroissant.
        $result = array_map(static function (array $r): array {
            $r['hours'] = round($r['hours'], 1);
            return $r;
        }, array_values($byResource));

        usort($result, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return $result;
    }

    /**
     * Instances de réservations APPROUVÉES qui débutent dans la fenêtre [from, until]
     * et qui n'ont pas encore reçu de rappel (reminder_sent_at IS NULL).
     * Utilisé par SendRemindersCommand.
     *
     * @return ReservationInstance[]
     */
    public function findInstancesToRemind(\DateTimeInterface $from, \DateTimeInterface $until): array
    {
        return $this->createQueryBuilder('i')
            ->join('i.series', 's')
            ->join('s.status', 'st')
            ->andWhere('i.reminderSentAt IS NULL')
            ->andWhere('i.startDate >= :from')
            ->andWhere('i.startDate <= :until')
            ->andWhere('st.id = :approved')
            ->setParameter('from', $from)
            ->setParameter('until', $until)
            ->setParameter('approved', ReservationStatus::APPROVED)
            ->orderBy('i.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
