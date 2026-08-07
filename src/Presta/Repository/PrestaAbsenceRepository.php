<?php

namespace App\Presta\Repository;

use App\Presta\Entity\PrestaAbsence;
use App\Presta\Entity\Prestataire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PrestaAbsence>
 *
 * @method PrestaAbsence|null find($id, $lockMode = null, $lockVersion = null)
 * @method PrestaAbsence|null findOneBy(array $criteria, array $orderBy = null)
 * @method PrestaAbsence[]    findAll()
 * @method PrestaAbsence[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PrestaAbsenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PrestaAbsence::class);
    }

    /**
     * Absences d'un prestataire, triées par date de début.
     *
     * @return PrestaAbsence[]
     */
    public function findByPrestataire(Prestataire $prestataire): array
    {
        return $this->findBy(['prestataire' => $prestataire], ['dateDebut' => 'ASC']);
    }

    /**
     * Absences d'un prestataire chevauchant une plage de dates (en UNE requête,
     * pour le calcul des créneaux sur tout un mois sans N+1).
     *
     * @return PrestaAbsence[]
     */
    public function findForPrestataireBetween(
        Prestataire $prestataire,
        \DateTimeInterface $start,
        \DateTimeInterface $end,
    ): array {
        return $this->createQueryBuilder('a')
            ->where('a.prestataire = :prestataire')
            ->andWhere('a.dateDebut <= :end')
            ->andWhere('a.dateFin >= :start')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult();
    }

    /**
     * Créneaux bloqués À VENIR d'un prestataire (dont la fin est postérieure à
     * maintenant), pour affichage dans l'agenda. Triés par date de début.
     *
     * @return PrestaAbsence[]
     */
    public function findUpcomingForPrestataire(Prestataire $prestataire): array
    {
        // dateFin >= début de journée → les créneaux bloqués d'AUJOURD'HUI
        // restent visibles toute la journée sur l'agenda (même si l'heure est
        // passée), en plus des créneaux à venir.
        return $this->createQueryBuilder('a')
            ->where('a.prestataire = :prestataire')
            ->andWhere('a.dateFin >= :todayStart')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('todayStart', new \DateTime('today'))
            ->orderBy('a.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(PrestaAbsence $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PrestaAbsence $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
