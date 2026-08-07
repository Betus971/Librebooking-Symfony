<?php

namespace App\Repository;

use App\Entity\Accessoire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Accessoire>
 */
class AccessoireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Accessoire::class);
    }

    /**
     * Accessoires actifs, triés par nom (pour la modale de réservation).
     *
     * @return Accessoire[]
     */
    public function findActiveOrdered(): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.actif = true')
            ->orderBy('a.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
