<?php

namespace App\Presta\Repository;

use App\Presta\Entity\PrestaCategorie;
use App\Presta\Entity\Prestataire;
use App\Presta\Entity\Service;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Service>
 */
class ServiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Service::class);
    }

    /**
     * Prestations actives d'une catégorie (tous prestataires), triées par libellé.
     *
     * @return Service[]
     */
    public function findActiveByCategorie(PrestaCategorie $categorie): array
    {
        return $this->findBy(
            ['categorie' => $categorie, 'isActive' => true],
            ['libelle' => 'ASC'],
        );
    }

    /**
     * Prestations actives d'un prestataire.
     *
     * @return Service[]
     */
    public function findActiveByPrestataire(Prestataire $prestataire): array
    {
        return $this->findBy(
            ['prestataire' => $prestataire, 'isActive' => true],
            ['libelle' => 'ASC'],
        );
    }

    /**
     * Toutes les prestations d'un prestataire (catalogue côté provider).
     *
     * @return Service[]
     */
    public function findByPrestataire(Prestataire $prestataire): array
    {
        return $this->findBy(
            ['prestataire' => $prestataire],
            ['libelle' => 'ASC'],
        );
    }

    public function save(Service $service, bool $flush = false): void
    {
        $this->getEntityManager()->persist($service);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Service $service, bool $flush = false): void
    {
        $this->getEntityManager()->remove($service);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
