<?php

namespace App\Domain\Reservation;

use App\Entity\ReservationAuditLog;
use App\Entity\ReservationSeries;
use App\Entity\ReservationStatus;
use App\Entity\User;
use App\Repository\ReservationInstanceRepository;
use App\Repository\ReservationSeriesRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Orchestrateur des transitions d'état d'une {@see ReservationSeries}.
 *
 * Source de vérité unique : {@see ReservationStatus::PENDING|APPROVED|REJECTED|CANCELLED}.
 * Chaque transition est tracée dans {@see ReservationAuditLog} au sein de la
 * même transaction que la mutation du statut (atomicité garantie par flush).
 */
class ReservationWorkflow
{
    private const FLOW = [
        'approve' => ['from' => [ReservationStatus::PENDING],                              'to' => ReservationStatus::APPROVED],
        'reject'  => ['from' => [ReservationStatus::PENDING],                              'to' => ReservationStatus::REJECTED],
        'cancel'  => ['from' => [ReservationStatus::PENDING, ReservationStatus::APPROVED], 'to' => ReservationStatus::CANCELLED],
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private ReservationInstanceRepository $instances,
        private ReservationSeriesRepository $seriesRepo,
    ) {
    }

    /**
     * Vrai si la transition est autorisée depuis le statut courant de la série.
     */
    public function can(string $transition, ReservationSeries $s): bool
    {
        $from = self::FLOW[$transition]['from'] ?? [];
        return in_array($s->getStatus()?->getId(), $from, true);
    }

    /**
     * Applique une transition, journalise l'événement et persiste le tout
     * dans une transaction unique (flush Doctrine).
     *
     * @param string           $transition  'approve' | 'reject' | 'cancel'
     * @param ReservationSeries $s
     * @param User|null        $actor       Auteur de l'action (null = système/CLI).
     * @param string|null      $reason      Motif libre (obligatoire en pratique pour un refus).
     */
    public function apply(
        string $transition,
        ReservationSeries $s,
        ?User $actor = null,
        ?string $reason = null,
    ): void {
        if (!$this->can($transition, $s)) {
            throw new \LogicException("Transition interdite: $transition");
        }

        $fromStatusId = $s->getStatus()?->getId();
        $toStatusId   = self::FLOW[$transition]['to'];

        // 1) Mutation du statut
        $s->setStatus($this->em->getReference(ReservationStatus::class, $toStatusId));
        $s->setLastModified(new \DateTimeImmutable());

        // 2) Trace d'audit dans la même unit-of-work
        $log = new ReservationAuditLog(
            series: $s,
            action: $transition,
            actor: $actor,
            fromStatusId: $fromStatusId,
            toStatusId: $toStatusId,
            reason: $reason,
        );
        $this->em->persist($log);

        // 3) Flush unique : Doctrine encapsule tout dans une transaction,
        //    donc l'UPDATE de la série et l'INSERT du log sont atomiques.
        $this->em->flush();
    }

    /**
     * Vérifie les règles métier (temporelles + statut) avant d'autoriser une action.
     * Lève \DomainException avec un message user-friendly sinon.
     */
    public function ensureAllowed(string $action, ReservationSeries $series): void
    {
        // 1) Bloquer les actions d'approbation/refus sur du passé.
        //    L'annulation reste possible pour laisser une trace admin (ex: no-show).
        if (in_array($action, ['approve', 'reject'], true)) {
            $now = new \DateTimeImmutable();

            if (!$this->instances->hasUpcoming($series, $now)) {
                throw new \DomainException('Action impossible : la réservation est déjà passée.');
            }
        }

        // 2) Règle de transition (même source que can() → pas de divergence)
        if (!$this->can($action, $series)) {
            throw new \DomainException(match ($action) {
                'approve' => "Action impossible : la réservation n'est pas en attente.",
                'reject'  => 'Action impossible : seule une réservation en attente peut être refusée.',
                'cancel'  => "Action impossible : statut incompatible avec l'annulation.",
                default   => "Transition interdite: $action",
            });
        }

        // 3) Règle spécifique : approve ne s'applique que si la ressource l'exige
        if ($action === 'approve' && !$this->seriesRepo->requiresApproval($series)) {
            throw new \DomainException("Cette réservation n'exige pas d'approbation.");
        }
    }
}
