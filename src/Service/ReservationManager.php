<?php

namespace App\Service;

use App\Dto\ReservationQuickDto;
use App\Entity\Accessoire;
use App\Entity\Resource;
use App\Entity\User;
use App\Entity\ReservationAccessoire;
use App\Entity\ReservationAuditLog;
use App\Entity\ReservationSeries;
use App\Entity\ReservationInstance;
use App\Entity\ReservationResource;
use App\Entity\ReservationStatus;
use App\Entity\ReservationType;
use App\Repository\AccessoireRepository;
use App\Repository\ReservationInstanceRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ReservationManager
{
    public function __construct(
        private EntityManagerInterface        $em,
        private ReservationInstanceRepository $instances,
        private AvailabilityChecker           $availability,
        private ReferenceNumberGenerator      $refGen,
        private AccessoireRepository          $accessoires,
    )
    {
    }

    /**
     * Crée une réservation simple SOUS VERROU CONCURRENTIEL PostgreSQL (RF-1).
     *
     * Logique extraite telle quelle de ReservationController::new() afin de
     * préserver strictement le comportement :
     *   - verrou `pg_advisory_xact_lock` sérialisant par ressource ;
     *   - re-check de disponibilité SOUS verrou (ferme la fenêtre de course) ;
     *   - statut initial PENDING/APPROVED selon `requiresApproval` ;
     *   - création série + lien ressource + instance + journal d'audit ;
     *   - le tout dans une transaction unique (flush atomique).
     *
     * Les pièces jointes restent gérées par l'appelant via $persistAttachments
     * (upload + flash = préoccupations HTTP). Le callback est invoqué dans la
     * transaction, juste après le persist de la série, comme avant.
     *
     * @param callable(ReservationSeries):void $persistAttachments
     * @throws \DomainException 'concurrent_booking' si le créneau est pris sous verrou.
     */
    public function createWithLock(
        Resource            $resource,
        User                $owner,
        ReservationQuickDto $dto,
        callable            $persistAttachments
    ): ReservationSeries {
        // Namespace arbitraire mais stable (évite les collisions avec d'autres
        // usages d'advisory locks dans l'appli). "RESV".
        $lockNamespace = 0x52455356;

        return $this->em->wrapInTransaction(function () use (
            $resource, $owner, $dto, $persistAttachments, $lockNamespace
        ): ReservationSeries {
            // Verrou Postgres : auto-libéré en fin de transaction.
            $this->em->getConnection()->executeStatement(
                'SELECT pg_advisory_xact_lock(:ns, :rid)',
                ['ns' => $lockNamespace, 'rid' => (int) $resource->getId()]
            );

            // Re-check SOUS verrou : une autre requête a pu insérer entre le
            // pré-check et l'acquisition du lock. C'est la fenêtre de race
            // qu'on ferme ici.
            if (!$this->availability->isFree($resource, $dto->start, $dto->end)) {
                throw new \DomainException('concurrent_booking');
            }

            // Statut initial : En attente si approbation requise, Confirmée sinon.
            $initialStatusId = $resource->isRequiresApproval()
                ? ReservationStatus::PENDING
                : ReservationStatus::APPROVED;

            $series = (new ReservationSeries())
                ->setTitle($dto->title)
                ->setDescription($dto->description)
                ->setNombreParticipants($dto->nombreParticipants)
                ->setOwner($owner)
                ->setType($this->em->getReference(ReservationType::class, ReservationType::STANDARD))
                ->setStatus($this->em->getReference(ReservationStatus::class, $initialStatusId));
            $this->em->persist($series);

            // Accessoires demandés (matériel mobile). Phase 1 : garde-fou de
            // stock SIMPLE (la demande d'une réservation ne peut pas dépasser le
            // stock total de l'accessoire). Le contrôle concurrentiel entre
            // réservations qui se chevauchent viendra en Phase 2 — il prendra
            // place ici, sous le même verrou.
            $this->persistAccessoires($series, $resource, $dto->accessoires);

            // Pièces jointes : déléguées à l'appelant (upload + flash restent HTTP).
            $persistAttachments($series);

            $link = (new ReservationResource())
                ->setSeries($series)
                ->setResource($resource)
                ->setResourceLevelId(1);
            $this->em->persist($link);

            $instance = (new ReservationInstance())
                ->setSeries($series)
                ->setStartDate($dto->start)
                ->setEndDate($dto->end)
                ->setReferenceNumber($this->refGen->generate());
            $this->em->persist($instance);

            // Trace d'audit de création dans la même unit-of-work.
            $this->em->persist(new ReservationAuditLog(
                series: $series,
                action: ReservationAuditLog::ACTION_CREATE,
                actor: $owner,
                fromStatusId: null,
                toStatusId: $initialStatusId,
            ));

            $this->em->flush();

            return $series;
        });
    }

    /**
     * Persiste les accessoires demandés pour une série et applique le garde-fou
     * de stock simple (Phase 1).
     *
     * @param array<int|string,int|string> $requested map accessoireId => quantité
     * @throws \DomainException 'accessoire_stock:<nom>:<dispo>' si une quantité
     *                          demandée dépasse le stock total de l'accessoire.
     *
     * Ignore silencieusement les accessoires inconnus, désactivés, ou non
     * rattachés à la ressource (voir {@see Accessoire::estDisponiblePour()}).
     */
    private function persistAccessoires(ReservationSeries $series, Resource $resource, array $requested): void
    {
        foreach ($requested as $accessoireId => $quantite) {
            $quantite = (int) $quantite;
            if ($quantite <= 0) {
                continue; // 0 ou vide = accessoire non demandé
            }

            $accessoire = $this->accessoires->find((int) $accessoireId);
            if (!$accessoire instanceof Accessoire || !$accessoire->isActif()) {
                continue; // accessoire inconnu ou désactivé : on ignore silencieusement
            }

            // Accessoire non prévu pour cette ressource (garde-fou serveur, au
            // cas où le filtrage JS aurait été contourné) : on ignore.
            if (!$accessoire->estDisponiblePour($resource)) {
                continue;
            }

            $dispo = $accessoire->getQuantiteDisponible();
            if ($dispo !== null && $quantite > $dispo) {
                throw new \DomainException(sprintf('accessoire_stock:%s:%d', $accessoire->getNom(), $dispo));
            }

            $ligne = (new ReservationAccessoire())
                ->setSeries($series)
                ->setAccessoire($accessoire)
                ->setQuantiteDemandee($quantite);
            $this->em->persist($ligne);
        }
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
