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
            ->setParameters([
                'start' => $start,
                'end' => $end,
                'scheduleId' => $scheduleId,
                'activeStatuses' => ReservationStatus::ACTIVE_STATUSES,
            ])
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
            ->setParameters([
                'start' => $start,
                'end' => $end,
                'resourceId' => $resourceId,
                'activeStatuses' => ReservationStatus::ACTIVE_STATUSES,
            ])
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
     * chevauche l'intervalle [start, end) pour la ressource donnée.
     *
     * Encapsule la détection de conflit utilisée par {@see \App\Service\AvailabilityChecker::isFree()}.
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
     * Vrai s'il reste au moins une instance à venir (startDate > $since) pour la série.
     *
     * Encapsule la vérification utilisée par {@see \App\Domain\Reservation\ReservationWorkflow::ensureAllowed()}.
     */
    public function hasUpcoming(ReservationSeries $series, \DateTimeInterface $since): bool
    {
        $count = (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->where('i.series = :s')
            ->andWhere('i.startDate > :since')
            ->setParameter('s', $series)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Instances chevauchant la plage [from, to) pour une liste de ressources,
     * avec série et ressources préchargées (vue planning API).
     *
     * @param int[] $resourceIds
     *
     * @return ReservationInstance[]
     */
    public function findForPlanningRange(array $resourceIds, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        if (empty($resourceIds)) {
            return [];
        }

        return $this->createQueryBuilder('ri')
            ->select('ri', 's', 'rr', 'r2')
            ->join('ri.series', 's')
            ->join('s.reservationResources', 'rr')
            ->join('rr.resource', 'r2')
            ->andWhere('r2.id IN (:ids)')
            ->andWhere('ri.endDate  > :from')
            ->andWhere('ri.startDate < :to')
            ->setParameter('ids', $resourceIds)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('ri.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
