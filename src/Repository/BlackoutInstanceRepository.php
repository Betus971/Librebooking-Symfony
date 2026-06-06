<?php

namespace App\Repository;

use App\Entity\BlackoutInstance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BlackoutInstanceRepository extends ServiceEntityRepository
{


    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlackoutInstance::class);
    }

//    public function busyIntervalsForResourcesBetween(array $resourceIds, \DateTimeInterface $start, \DateTimeInterface $end): array
//    {
//        $dql = 'SELECT r.id AS rid, bi.startDate AS s, bi.endDate AS e
//            FROM App\Entity\BlackoutInstance bi
//            JOIN bi.series bs
//            JOIN bs.resource r
//            WHERE r.id IN (:ids) AND bi.startDate < :end AND bi.endDate > :start';
//        return $this->getEntityManager()
//            ->createQuery($dql)
//            ->setParameter('ids', $resourceIds)
//            ->setParameter('start', $start)
//            ->setParameter('end', $end)
//            ->getArrayResult();
//    }

    public function busyIntervalsForResourcesBetween(array $resourceIds, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        // Si la liste est vide, on renvoie rien pour éviter une erreur SQL
        if (empty($resourceIds)) {
            return [];
        }

        return $this->createQueryBuilder('bi')
            ->select('r.id AS rid, bi.startDate AS s, bi.endDate AS e')
            ->join('bi.series', 'bs')
            ->join('bs.resource', 'r')
            ->where('r.id IN (:ids)')
            ->andWhere('bi.startDate < :end')
            ->andWhere('bi.endDate > :start')
            ->setParameter('ids', $resourceIds)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getArrayResult();
    }


    public function hasBlackout($resource, \DateTimeInterface $start, \DateTimeInterface $end): bool
    {
        $count = $this->createQueryBuilder('i') // 'i' pour Instance
        ->select('count(i.id)')
            ->join('i.series', 's')             // On rejoint la Série pour vérifier la ressource
            ->andWhere('s.resource = :resource')
            // Logique de chevauchement (Overlap) :
            ->andWhere('i.startDate < :end')    // Le blackout commence avant que la résa finisse
            ->andWhere('i.endDate > :start')    // Le blackout finit après que la résa commence
            ->setParameter('resource', $resource)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

}
