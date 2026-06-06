<?php

namespace App\Repository;

use App\Entity\ReservationInstance;
use App\Entity\ReservationStatus;
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
}
