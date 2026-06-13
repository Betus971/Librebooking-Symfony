<?php

namespace App\Repository;

use App\Entity\Resource;
use App\Entity\User;
use App\Entity\WaitlistRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WaitlistRequest>
 */
class WaitlistRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WaitlistRequest::class);
    }

    /**
     * Demandes en attente pour une ressource dont le créneau chevauche
     * [start, end), en ordre FIFO (plus ancienne d'abord).
     *
     * @return WaitlistRequest[]
     */
    public function findWaitingForResourceWindow(Resource $resource, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.resource = :res')
            ->andWhere('w.status = :waiting')
            ->andWhere('w.startDate < :end')
            ->andWhere('w.endDate > :start')
            ->setParameter('res', $resource)
            ->setParameter('waiting', WaitlistRequest::STATUS_WAITING)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('w.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Vrai si l'utilisateur a déjà une demande en attente identique
     * (même ressource + même créneau exact) — pour éviter les doublons.
     */
    public function existsWaitingFor(User $user, Resource $resource, \DateTimeInterface $start, \DateTimeInterface $end): bool
    {
        $count = (int) $this->createQueryBuilder('w')
            ->select('COUNT(w.id)')
            ->andWhere('w.user = :user')
            ->andWhere('w.resource = :res')
            ->andWhere('w.status = :waiting')
            ->andWhere('w.startDate = :start')
            ->andWhere('w.endDate = :end')
            ->setParameter('user', $user)
            ->setParameter('res', $resource)
            ->setParameter('waiting', WaitlistRequest::STATUS_WAITING)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
