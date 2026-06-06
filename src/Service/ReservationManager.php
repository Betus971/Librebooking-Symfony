<?php

namespace App\Service;

use App\Entity\Resource;
use App\Entity\User;
use App\Entity\ReservationSeries;
use App\Entity\ReservationInstance;
use App\Entity\ReservationResource;
use App\Repository\ReservationInstanceRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ReservationManager
{
    public function __construct(
        private EntityManagerInterface        $em,
        private ReservationInstanceRepository $instances
    )
    {
    }

    /**
     * Crée une réservation simple (non récurrente).
     * @throws \InvalidArgumentException si une règle métier est violée.
     */
    public function createSimpleReservation(
        Resource           $resource,
        User               $owner,
        string             $title,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        ?string            $description = null
    ): ReservationSeries
    {
        if ($end <= $start) {
            throw new \InvalidArgumentException('Heure de fin <= heure de début.');
        }

        // Alignement sur l'incrément (minutes)
        if (null !== $resource->getMinIncrement()) {
            $inc = (int)$resource->getMinIncrement();
            $startMin = (int)$start->format('i');
            $endMin = (int)$end->format('i');
            if (($startMin % $inc) !== 0 || ($endMin % $inc) !== 0) {
                throw new \InvalidArgumentException("Créneaux non alignés sur {$inc} minutes.");
            }
        }

        // Durée min/max
        $minutes = (int)round(($end->getTimestamp() - $start->getTimestamp()) / 60);
        if (null !== $resource->getMinDuration() && $minutes < $resource->getMinDuration()) {
            throw new \InvalidArgumentException("Durée < minimum ({$resource->getMinDuration()} min).");
        }
        if (null !== $resource->getMaxDuration() && $minutes > $resource->getMaxDuration()) {
            throw new \InvalidArgumentException("Durée > maximum ({$resource->getMaxDuration()} min).");
        }

        // Multi-jour interdit ?
        if (!$resource->isAllowMultiday() && $start->format('Y-m-d') !== $end->format('Y-m-d')) {
            throw new \InvalidArgumentException('Les réservations multi-jours sont interdites pour cette ressource.');
        }

        // Chevauchement
        if ($this->instances->hasOverlap($resource, $start, $end, null)) {
            throw new \InvalidArgumentException('Chevauchement avec une réservation existante.');
        }

        // --- Série
        $series = (new ReservationSeries())
            ->setTitle($title)
            ->setDescription($description)
            ->setOwner($owner);
        // TODO: status/type si tu as des champs dédiés (PENDING/APPROVED…)

        // --- Instance (occurrence réelle)
        $instance = (new ReservationInstance())
            ->setSeries($series)
            ->setStartDate($start)
            ->setEndDate($end)
            ->setReferenceNumber(bin2hex(random_bytes(8)));

        // --- Lien série <-> ressource
        $pivot = (new ReservationResource())
            ->setSeries($series)
            ->setResource($resource);

        $this->em->persist($series);
        $this->em->persist($instance);
        $this->em->persist($pivot);
        $this->em->flush();

        return $series;
    }
}
