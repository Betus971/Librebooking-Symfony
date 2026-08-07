<?php

namespace App\Presta\Repository;

use App\Presta\Entity\PrestaCategorie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PrestaCategorie>
 */
class PrestaCategorieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PrestaCategorie::class);
    }

    /**
     * Catégories triées par nom (pour les listes et selects).
     *
     * @return PrestaCategorie[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(PrestaCategorie $categorie, bool $flush = false): void
    {
        $this->getEntityManager()->persist($categorie);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PrestaCategorie $categorie, bool $flush = false): void
    {
        $this->getEntityManager()->remove($categorie);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
