<?php

namespace App\Presta\Repository;

use App\Presta\Entity\Prestataire;
use App\Presta\Entity\Service;
use App\Presta\Entity\Session;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Session>
 */
class SessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Session::class);
    }

    /**
     * Sessions futures d'un service (vue groupe), triées par date.
     *
     * @return Session[]
     */
    public function findFutureByService(Service $service): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.service = :service')
            ->andWhere('s.dateDebut > :now')
            ->setParameter('service', $service)
            ->setParameter('now', new \DateTime())
            ->orderBy('s.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Toutes les séances d'un prestataire (catalogue côté provider), triées.
     *
     * @return Session[]
     */
    public function findByPrestataire(Prestataire $prestataire): array
    {
        return $this->findBy(['prestataire' => $prestataire], ['dateDebut' => 'ASC']);
    }

    /**
     * Séances de GROUPE uniquement d'un prestataire (page « Mes séances de
     * groupe »), triées de la plus récente à la plus ancienne. Exclut les RDV
     * individuels qui ne doivent pas apparaître sur cette page.
     *
     * @return Session[]
     */
    public function findGroupByPrestataire(Prestataire $prestataire): array
    {
        return $this->createQueryBuilder('s')
            ->join('s.service', 'srv')
            ->where('s.prestataire = :prestataire')
            ->andWhere('srv.type = :typeGroupe')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('typeGroupe', Service::TYPE_GROUPE)
            ->orderBy('s.dateDebut', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Séances futures à afficher dans l'agenda du prestataire :
     * toutes les séances de groupe + les RDV individuels ayant au moins
     * un inscrit. Triées par date.
     *
     * @return Session[]
     */
    public function findUpcomingForAgenda(Prestataire $prestataire): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.service', 'srv')
            ->where('s.prestataire = :prestataire')
            ->andWhere('s.dateDebut >= :today')
            ->andWhere('srv.type = :typeGroup OR (srv.type = :typeIndiv AND s.nbInscrits > 0)')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('today', new \DateTime('today'))
            ->setParameter('typeGroup', Service::TYPE_GROUPE)
            ->setParameter('typeIndiv', Service::TYPE_INDIVIDUEL)
            ->orderBy('s.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Y a-t-il une séance « bloquante » du prestataire qui CHEVAUCHE la plage
     * [start, end] ? (séances de groupe + RDV individuels déjà pris).
     * Sert au re-check anti double-réservation au moment de confirmer un RDV.
     */
    public function hasConflictForPrestataire(Prestataire $prestataire, \DateTimeInterface $start, \DateTimeInterface $end): bool
    {
        $count = (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->leftJoin('s.service', 'srv')
            ->where('s.prestataire = :prestataire')
            ->andWhere('s.dateDebut < :end')   // chevauchement : début1 < fin2
            ->andWhere('s.dateFin > :start')   //              ET début2 < fin1
            ->andWhere('srv.type = :typeGroup OR (srv.type = :typeIndiv AND s.nbInscrits > 0)')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('typeGroup', Service::TYPE_GROUPE)
            ->setParameter('typeIndiv', Service::TYPE_INDIVIDUEL)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function save(Session $session, bool $flush = false): void
    {
        $this->getEntityManager()->persist($session);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Session $session, bool $flush = false): void
    {
        $this->getEntityManager()->remove($session);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Sessions qui « bloquent » l'agenda d'un prestataire sur une plage de dates
     * (toutes les sessions de groupe + les RDV individuels déjà pris). Sert au
     * calcul des créneaux disponibles : on charge TOUTE la plage en UNE requête
     * (évite le N+1 quand on génère un mois entier).
     *
     * @return Session[]
     */
    public function findBlockingForPrestataireBetween(
        Prestataire $prestataire,
        \DateTimeInterface $start,
        \DateTimeInterface $end,
    ): array {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.service', 'srv')
            ->where('s.prestataire = :prestataire')
            ->andWhere('s.dateDebut >= :start')
            ->andWhere('s.dateDebut <= :end')
            ->andWhere('srv.type = :typeGroup OR (srv.type = :typeIndiv AND s.nbInscrits > 0)')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('typeGroup', Service::TYPE_GROUPE)
            ->setParameter('typeIndiv', Service::TYPE_INDIVIDUEL)
            ->getQuery()
            ->getResult();
    }
}
