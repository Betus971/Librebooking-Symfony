<?php
namespace App\Presta\Entity;

use App\Presta\Repository\InscriptionRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use App\Entity\User; // Link to the generic booking-D user

#[ORM\Entity(repositoryClass: InscriptionRepository::class)]
#[ORM\Table(name: 'presta_inscription')]
class Inscription
{
    /** En attente de validation par le prestataire (prestation à approbation). */
    public const STATUT_PENDING   = 'PENDING';
    /** Réservation confirmée (immédiate ou après validation). */
    public const STATUT_CONFIRMED = 'CONFIRMED';
    /** Annulée (par le client ou refusée/annulée par le prestataire). */
    public const STATUT_CANCELLED = 'CANCELLED';
    /** Sur liste d'attente (si la séance de groupe est complète). */
    public const STATUT_WAITLIST  = 'WAITLIST';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'inscriptions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Session $session = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $client = null;

    #[ORM\Column(length: 50)]
    private ?string $statut = self::STATUT_CONFIRMED; // PENDING, CONFIRMED, CANCELLED

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateCreation = null;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSession(): ?Session
    {
        return $this->session;
    }

    public function setSession(?Session $session): static
    {
        $this->session = $session;
        return $this;
    }

    public function getClient(): ?User
    {
        return $this->client;
    }

    public function setClient(?User $client): static
    {
        $this->client = $client;
        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    public function isPending(): bool
    {
        return self::STATUT_PENDING === $this->statut;
    }

    public function isConfirmed(): bool
    {
        return self::STATUT_CONFIRMED === $this->statut;
    }

    public function isCancelled(): bool
    {
        return self::STATUT_CANCELLED === $this->statut;
    }

    public function isWaitlisted(): bool
    {
        return self::STATUT_WAITLIST === $this->statut;
    }

    /** Active = compte comme un RDV en cours (en attente, sur liste d'attente, ou confirmé). */
    public function isActive(): bool
    {
        return $this->isPending() || $this->isConfirmed() || $this->isWaitlisted();
    }

    public function getDateCreation(): ?\DateTimeInterface
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTimeInterface $dateCreation): static
    {
        $this->dateCreation = $dateCreation;
        return $this;
    }
}
