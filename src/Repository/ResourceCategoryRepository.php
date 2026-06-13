<?php

namespace App\Repository;

use App\Entity\ResourceCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ResourceCategory>
 */
class ResourceCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ResourceCategory::class);
    }

    public function findForHome(): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.resources', 'r', 'WITH', 'r.isActive = :active')
            ->setParameter('active', true)
            ->addSelect('COUNT(r.id) AS resourceCount')
            ->groupBy('c.id')
            ->orderBy('c.name', 'ASC');

        // ⚠️ c'est bien getQuery()->getResult()
        return $qb->getQuery()->getResult();
        // Si tu préfères des tableaux scalaires uniquement :
        // return $qb->getQuery()->getArrayResult();
    }



}
