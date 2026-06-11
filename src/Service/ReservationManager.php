<?php

namespace App\Service;

use App\Entity\ReservationAuditLog;
use App\Entity\ReservationInstance;
use App\Entity\ReservationResource;
use App\Entity\ReservationSeries;
use App\Entity\ReservationStatus;
use App\Entity\ReservationType;
use App\Entity\Resource;
use App\Entity\User;
use App\Service\Exception\ConcurrentBookingException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Orchestre la création d'une réservation : verrou concurrentiel PostgreSQL,
 * re-check de disponibilité sous verrou, création des entités (série, instance,
 * lien ressource, journal d'audit) et persistance atomique.
 *
 * Cette logique vivait auparavant dans ReservationController::new() : elle est
 * désormais isolée ici, le contrôleur se limitant au routage HTTP.
 */
final class ReservationManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AvailabilityChecker $availability,
        private readonly ReferenceNumberGenerator $refGen,
    ) {
    }

    /**
     * Crée une réservation simple sous verrou concurrentiel, dans une transaction unique.
     *
     * Le statut initial est déterminé par la ressource : PENDING si elle exige une
     * approbation, APPROVED (auto-validée) sinon.
     *
     * @param callable(ReservationSeries):void|null $persistAttachments
     *        Callback optionnel exécuté DANS la transaction, juste après la persistance
     *        de la série : permet au contrôleur de rattacher les pièces jointes uploadées.
     *
     * @throws ConcurrentBookingException si le créneau a été pris entre le pré-check
     *                                    et l'acquisition du verrou.
     */
    public function createWithLock(
        Resource $resource,
        User $owner,
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        string $title,
        ?string $description = null,
        ?callable $persistAttachments = null,
    ): ReservationSeries {
        return $this->em->wrapInTransaction(function () use (
            $resource,
            $owner,
            $start,
            $end,
            $title,
            $description,
            $persistAttachments,
        ): ReservationSeries {
            // Verrou Postgres : auto-libéré en fin de transaction.
            // Namespace arbitraire mais stable (« RESV ») pour éviter les collisions
            // avec d'autres usages d'advisory locks.
            $lockNamespace = 0x52455356; // "RESV"
            $this->em->getConnection()->executeStatement(
                'SELECT pg_advisory_xact_lock(:ns, :rid)',
                ['ns' => $lockNamespace, 'rid' => (int) $resource->getId()]
            );

            // Re-check SOUS verrou : une autre requête a pu insérer entre le
            // pré-check et l'acquisition du lock. C'est la fenêtre de race fermée ici.
            if (!$this->availability->isFree($resource, $start, $end)) {
                throw new ConcurrentBookingException();
            }

            // Statut initial : En attente si approbation requise, Confirmée sinon (auto-approve).
            $initialStatusId = $resource->isRequiresApproval()
                ? ReservationStatus::PENDING
                : ReservationStatus::APPROVED;

            $series = (new ReservationSeries())
                ->setTitle($title)
                ->setDescription($description)
                ->setOwner($owner)
                ->setType($this->em->getReference(ReservationType::class, ReservationType::STANDARD))
                ->setStatus($this->em->getReference(ReservationStatus::class, $initialStatusId));
            $this->em->persist($series);

            // Pièces jointes (gérées par l'appelant, dans la même transaction).
            if (null !== $persistAttachments) {
                $persistAttachments($series);
            }

            $link = (new ReservationResource())
                ->setSeries($series)
                ->setResource($resource)
                ->setResourceLevelId(1);
            $this->em->persist($link);

            $instance = (new ReservationInstance())
                ->setSeries($series)
                ->setStartDate($start)
                ->setEndDate($end)
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
}
