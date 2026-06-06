<?php

namespace App\Repository;

use App\Dto\ResourceSearchCriteria;
use App\Entity\Resource;
use App\Entity\ResourceCategory;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Resource>
 */
class ResourceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Resource::class);
    }

    //    /**
    //     * @return Resource[] Returns an array of Resource objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('r.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Resource
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
    public function findAllCategories(): array
    {
        return $this->createQueryBuilder('r')
            ->select('DISTINCT c.id, c.name')  // Sélectionne uniquement les colonnes nécessaires
            ->join('r.category', 'c')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }


    public function findAllForSelect(): array
    {
        return $this->createQueryBuilder('r')
            ->select('r.id AS id', 'r.name AS name')   // <-- adapte "name" si ton champ s’appelle autrement (label, title...)
            ->orderBy('r.name', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    public function findActiveByCategory(ResourceCategory $category): array
    {
        return $this->createQueryBuilder('r')
            ->select('r')
            ->andWhere('r.isActive = :active')->setParameter('active', true)
            ->andWhere('r.category = :cat')->setParameter('cat', $category)
            ->orderBy('r.name', 'ASC')
            ->getQuery()->getResult();
    }

    public function findActiveOrdered(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.isActive = :active')
            ->setParameter('active', true)   // ✅ booléen, pas = 1
            ->orderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
    public function findForIndex(array $filters, ?User $user = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.schedule', 's')->addSelect('s')
            ->orderBy('r.name', 'ASC');

        // Filtres de base (recherche, statut, planning)
        if (!empty($filters['q'])) {
            $qb->andWhere('r.name LIKE :q OR r.location LIKE :q')
                ->setParameter('q', '%' . $filters['q'] . '%');
        }
        if (!empty($filters['schedule'])) {
            $qb->andWhere('s.id = :sid')->setParameter('sid', $filters['schedule']);
        }
        if (($filters['status'] ?? 'all') !== 'all') {
            $qb->andWhere('r.isActive = :act')->setParameter('act', $filters['status'] === 'active');
        }

        // --- 🛡️ FILTRE RBAC (CLOISONNEMENT PAR GROUPE) ---
        // Si l'utilisateur est fourni et qu'il N'EST PAS Super Admin
        if ($user && !in_array('ROLE_SUPER_ADMIN', $user->getRoles())) {
            $qb->join('r.resourceGroup', 'rg')
                ->join('rg.users', 'u')
                ->andWhere('u = :user')
                ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }


    public function search(ResourceSearchCriteria $c): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.category', 'cat')->addSelect('cat')
            ->orderBy('r.name','ASC');

        if ($c->onlyActive !== null) {
            $qb->andWhere('r.isActive = :act')->setParameter('act', $c->onlyActive);
        }
        if ($c->typeId) {
            $qb->andWhere('cat.id = :tid')->setParameter('tid', $c->typeId);
        }
        if ($c->minCapacity) {
            $qb->andWhere('r.maxParticipants >= :cap')->setParameter('cap', $c->minCapacity);
        }
        return $qb->getQuery()->getResult();
    }

}
