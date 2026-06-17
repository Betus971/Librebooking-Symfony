<?php

namespace App\Service;

use App\Entity\ReservationSeries;
use App\Entity\Resource;
use App\Entity\User;
use App\Entity\WaitlistRequest;
use App\Notification\ReservationNotifier;
use App\Repository\WaitlistRequestRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Gestion de la liste d'attente sur les créneaux de ressources.
 */
final class WaitlistService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly WaitlistRequestRepository $repo,
        private readonly ReservationNotifier $notifier,
    ) {
    }

    /**
     * Inscrit un utilisateur en liste d'attente pour un créneau (idempotent :
     * ne crée pas de doublon si une demande identique est déjà en attente).
     */
    public function join(User $user, Resource $resource, \DateTimeInterface $start, \DateTimeInterface $end): WaitlistRequest
    {
        if ($existing = $this->repo->findOneBy([
            'user'      => $user,
            'resource'  => $resource,
            'status'    => WaitlistRequest::STATUS_WAITING,
        ])) {
            // Affinage : on vérifie aussi la fenêtre exacte côté repository.
            if ($this->repo->existsWaitingFor($user, $resource, $start, $end)) {
                return $existing;
            }
        }

        $request = new WaitlistRequest($user, $resource, $start, $end);
        $this->em->persist($request);
        $this->em->flush();

        return $request;
    }

    /**
     * À appeler après l'ANNULATION d'une série : notifie (FIFO) les demandeurs
     * en attente dont le créneau chevauche un créneau désormais libéré.
     */
    public function notifyForFreedSeries(ReservationSeries $series): void
    {
        $touched = false;

        foreach ($series->getReservationResources() as $rr) {
            $resource = $rr->getResource();
            if (null === $resource) {
                continue;
            }

            foreach ($series->getInstances() as $instance) {
                $waiting = $this->repo->findWaitingForResourceWindow(
                    $resource,
                    $instance->getStartDate(),
                    $instance->getEndDate()
                );

                foreach ($waiting as $request) {
                    $this->notifier->waitlistOpened($request);
                    $request->setStatus(WaitlistRequest::STATUS_NOTIFIED);
                    $request->setNotifiedAt(new \DateTimeImmutable());
                    $touched = true;
                }
            }
        }

        if ($touched) {
            $this->em->flush();
        }
    }
}
