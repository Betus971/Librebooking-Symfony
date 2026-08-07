<?php

namespace App\Presta\Repository;

use App\Entity\User;
use App\Presta\Entity\Inscription;
use App\Presta\Entity\Prestataire;
use App\Presta\Entity\Service;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Inscription>
 */
class InscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Inscription::class);
    }

    /**
     * Inscriptions d'un client, séance et prestataire chargés (évite le N+1
     * dans « Mes réservations »), les plus récentes d'abord.
     *
     * @return Inscription[]
     */
    public function findForClient(User $client): array
    {
        return $this->createQueryBuilder('i')
            ->addSelect('s', 'srv')
            ->leftJoin('i.session', 's')
            ->leftJoin('s.service', 'srv')
            ->where('i.client = :client')
            ->setParameter('client', $client)
            ->orderBy('s.dateDebut', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Prochaine prestation à venir d'un client : inscription non annulée dont la
     * séance débute maintenant ou plus tard, la plus proche d'abord. Séance +
     * service chargés pour l'aperçu du portail d'accueil.
     */
    public function findNextForClient(User $client): ?Inscription
    {
        return $this->createQueryBuilder('i')
            ->addSelect('s', 'srv')
            ->leftJoin('i.session', 's')
            ->leftJoin('s.service', 'srv')
            ->where('i.client = :client')
            ->andWhere('i.statut != :cancelled')
            ->andWhere('s.dateDebut >= :now')
            ->setParameter('client', $client)
            ->setParameter('cancelled', 'CANCELLED')
            ->setParameter('now', new \DateTime())
            ->orderBy('s.dateDebut', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Le client a-t-il déjà un RDV INDIVIDUEL actif (en attente ou confirmé, non
     * passé) chez ce prestataire ? Sert à la règle « un seul RDV actif à la fois »
     * (les séances de groupe ne sont pas comptées).
     */
    public function hasActiveIndividualBooking(User $client, Prestataire $prestataire): bool
    {
        $count = (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->join('i.session', 's')
            ->join('s.service', 'srv')
            ->where('i.client = :client')
            ->andWhere('s.prestataire = :prestataire')
            ->andWhere('srv.type = :type')
            ->andWhere('i.statut IN (:actifs)')
            ->andWhere('s.dateDebut >= :now')
            ->setParameter('client', $client)
            ->setParameter('prestataire', $prestataire)
            ->setParameter('type', Service::TYPE_INDIVIDUEL)
            ->setParameter('actifs', [Inscription::STATUT_PENDING, Inscription::STATUT_CONFIRMED])
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Statistiques pour le tableau de bord prestataire
     */
    public function getProviderStats(Prestataire $prestataire): array
    {
        $now = new \DateTime();
        $startOfMonth = (clone $now)->modify('first day of this month')->setTime(0, 0);
        $endOfMonth = (clone $now)->modify('last day of this month')->setTime(23, 59, 59);

        // Nombre de réservations actives ce mois-ci
        $reservationsCeMois = (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->join('i.session', 's')
            ->where('s.prestataire = :prestataire')
            ->andWhere('s.dateDebut >= :start')
            ->andWhere('s.dateDebut <= :end')
            ->andWhere('i.statut IN (:actifs)')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('start', $startOfMonth)
            ->setParameter('end', $endOfMonth)
            ->setParameter('actifs', [Inscription::STATUT_PENDING, Inscription::STATUT_CONFIRMED, Inscription::STATUT_WAITLIST])
            ->getQuery()
            ->getSingleScalarResult();

        // Nouveaux inscrits en attente
        $enAttente = (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->join('i.session', 's')
            ->where('s.prestataire = :prestataire')
            ->andWhere('i.statut = :pending')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('pending', Inscription::STATUT_PENDING)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'reservations_ce_mois' => $reservationsCeMois,
            'en_attente' => $enAttente,
        ];
    }
}
