<?php

namespace App\Presta\Repository;

use App\Presta\Entity\PlageHoraire;
use App\Presta\Entity\Prestataire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlageHoraire>
 */
class PlageHoraireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlageHoraire::class);
    }

    /**
     * Toutes les plages horaires d'un prestataire (tous jours confondus),
     * triées par jour puis heure de début. À indexer par jourSemaine côté
     * appelant pour générer les créneaux sans requête par jour.
     *
     * @return PlageHoraire[]
     */
    public function findByPrestataire(Prestataire $prestataire): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->orderBy('p.jourSemaine', 'ASC')
            ->addOrderBy('p.heureDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Supprime toutes les plages horaires d'un prestataire en une seule
     * requête (DELETE en masse). Renvoie le nombre de lignes supprimées.
     */
    public function deleteAllForPrestataire(Prestataire $prestataire): int
    {
        return (int) $this->createQueryBuilder('p')
            ->delete()
            ->where('p.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->getQuery()
            ->execute();
    }

    public function save(PlageHoraire $plage, bool $flush = false): void
    {
        $this->getEntityManager()->persist($plage);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }

    public function remove(PlageHoraire $plage, bool $flush = false): void
    {
        $this->getEntityManager()->remove($plage);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
