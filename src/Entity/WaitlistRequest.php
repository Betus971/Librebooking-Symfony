<?php

namespace App\Entity;

use App\Repository\WaitlistRequestRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Demande de mise en liste d'attente sur un créneau d'une ressource.
 *
 * Quand un créneau est indisponible, l'utilisateur s'inscrit ; à l'annulation
 * d'une réservation chevauchant ce créneau, le {@see \App\Service\WaitlistService}
 * notifie les demandeurs en attente (FIFO).
 */
#[ORM\Entity(repositoryClass: WaitlistRequestRepository::class)]
#[ORM\Table(name: 'waitlist_requests')]
#[ORM\Index(name: 'idx_waitlist_resource_window', columns: ['resource_id', 'start_date', 'end_date'])]
#[ORM\Index(name: 'idx_waitlist_status', columns: ['status'])]
class WaitlistRequest
{
    public const STATUS_WAITING   = 'waiting';
    public const STATUS_NOTIFIED  = 'notified';
    public const STATUS_FULFILLED = 'fulfilled';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Resource::class)]
    #[ORM\JoinColumn(name: 'resource_id', nullable: false, onDelete: 'CASCADE')]
    private Resource $resource;

    #[ORM\Column(name: 'start_date', type: 'datetime_immutable')]
    private \DateTimeImmutable $startDate;

    #[ORM\Column(name: 'end_date', type: 'datetime_immutable')]
    private \DateTimeImmutable $endDate;

    #[ORM\Column(length: 16)]
    private string $status = self::STATUS_WAITING;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'notified_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $notifiedAt = null;

    public function __construct(User $user, Resource $resource, \DateTimeInterface $startDate, \DateTimeInterface $endDate)
    {
        $this->user = $user;
        $this->resource = $resource;
        $this->startDate = \DateTimeImmutable::createFromInterface($startDate);
        $this->endDate = \DateTimeImmutable::createFromInterface($endDate);
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getResource(): Resource
    {
        return $this->resource;
    }

    public function getStartDate(): \DateTimeImmutable
    {
        return $this->startDate;
    }

    public function getEndDate(): \DateTimeImmutable
    {
        return $this->endDate;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getNotifiedAt(): ?\DateTimeImmutable
    {
        return $this->notifiedAt;
    }

    public function setNotifiedAt(?\DateTimeImmutable $notifiedAt): static
    {
        $this->notifiedAt = $notifiedAt;

        return $this;
    }
}
