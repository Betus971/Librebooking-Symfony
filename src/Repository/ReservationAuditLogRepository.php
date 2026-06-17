<?php

namespace App\Repository;

use App\Entity\ReservationAuditLog;
use App\Entity\ReservationSeries;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReservationAuditLog>
 */
class ReservationAuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReservationAuditLog::class);
    }

    /**
     * Retourne l'historique complet d'une série, du plus ancien au plus récent.
     *
     * @return ReservationAuditLog[]
     */
    public function findForSeries(ReservationSeries $series): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.series = :series')
            ->setParameter('series', $series)
            ->orderBy('l.occurredAt', 'ASC')
            ->addOrderBy('l.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Dernière entrée d'audit pour une série (action courante).
     */
    public function findLastForSeries(ReservationSeries $series): ?ReservationAuditLog
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.series = :series')
            ->setParameter('series', $series)
            ->orderBy('l.occurredAt', 'DESC')
            ->addOrderBy('l.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
