<?php

namespace App\Presta\Repository;

use App\Entity\User;
use App\Presta\Entity\Prestataire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Prestataire>
 */
class PrestataireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Prestataire::class);
    }

    /**
     * Prestataires actifs (annuaire client).
     *
     * @return Prestataire[]
     */
    public function findActive(): array
    {
        return $this->findBy(['isActive' => true], ['nom' => 'ASC']);
    }

    public function findOneByUser(User $user): ?Prestataire
    {
        return $this->findOneBy(['user' => $user]);
    }

    public function save(Prestataire $prestataire, bool $flush = false): void
    {
        $this->getEntityManager()->persist($prestataire);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
