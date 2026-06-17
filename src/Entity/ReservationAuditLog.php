<?php

namespace App\Entity;

use App\Repository\ReservationAuditLogRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Journal d'audit métier append-only pour les réservations.
 *
 * Usage : écrit dans la même transaction que le changement d'état par
 * {@see \App\Domain\Reservation\ReservationWorkflow::apply()}.
 *
 * Conçu pour être immuable une fois persisté : aucun setter n'est exposé,
 * toutes les valeurs sont fixées dans le constructeur. L'atomicité est
 * garantie par le flush Doctrine (wrapper de transaction implicite).
 */
#[ORM\Entity(repositoryClass: ReservationAuditLogRepository::class)]
#[ORM\Table(name: 'reservation_audit_logs')]
#[ORM\Index(name: 'idx_ral_series', columns: ['series_id'])]
#[ORM\Index(name: 'idx_ral_occurred_at', columns: ['occurred_at'])]
class ReservationAuditLog
{
    public const ACTION_CREATE  = 'create';
    public const ACTION_APPROVE = 'approve';
    public const ACTION_REJECT  = 'reject';
    public const ACTION_CANCEL  = 'cancel';
    public const ACTION_UPDATE  = 'update';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    private ?int $id = null;

    /**
     * ON DELETE SET NULL : si la série est supprimée (rare), on conserve la
     * trace d'audit — la rétention légale prime sur la cohérence FK.
     */
    #[ORM\ManyToOne(targetEntity: ReservationSeries::class)]
    #[ORM\JoinColumn(name: 'series_id', nullable: true, onDelete: 'SET NULL')]
    private ?ReservationSeries $series;

    /**
     * Acteur à l'origine de l'action. Nullable pour couvrir les actions
     * système (import, batch, CLI) sans utilisateur connecté.
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'actor_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $actor;

    #[ORM\Column(length: 32)]
    private string $action;

    #[ORM\Column(name: 'from_status_id', type: 'smallint', options: ['unsigned' => true], nullable: true)]
    private ?int $fromStatusId;

    #[ORM\Column(name: 'to_status_id', type: 'smallint', options: ['unsigned' => true], nullable: true)]
    private ?int $toStatusId;

    /**
     * Motif libre (ex : raison de refus saisie par un admin).
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reason;

    /**
     * Payload structuré pour les actions `update` (diff before/after) ou
     * pour tout complément contextuel (IP, canal d'appel, etc.).
     *
     * @var array<string,mixed>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $payload;

    #[ORM\Column(name: 'occurred_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $occurredAt;

    /**
     * @param array<string,mixed>|null $payload
     */
    public function __construct(
        ?ReservationSeries $series,
        string $action,
        ?User $actor = null,
        ?int $fromStatusId = null,
        ?int $toStatusId = null,
        ?string $reason = null,
        ?array $payload = null,
    ) {
        $this->series = $series;
        $this->action = $action;
        $this->actor = $actor;
        $this->fromStatusId = $fromStatusId;
        $this->toStatusId = $toStatusId;
        $this->reason = $reason;
        $this->payload = $payload;
        $this->occurredAt = new \DateTimeImmutable();
    }

    // --- Accesseurs en lecture seule (pas de setter : audit append-only) ---

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSeries(): ?ReservationSeries
    {
        return $this->series;
    }

    public function getActor(): ?User
    {
        return $this->actor;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getFromStatusId(): ?int
    {
        return $this->fromStatusId;
    }

    public function getToStatusId(): ?int
    {
        return $this->toStatusId;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getPayload(): ?array
    {
        return $this->payload;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
