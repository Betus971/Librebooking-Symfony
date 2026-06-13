<?php
namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
#[ORM\Entity]
#[ORM\Table(name: 'reservation_statuses')]
class ReservationStatus
{
    // IDs stables définis en base (voir migration de seed au déploiement)
    const PENDING   = 1; // En attente
    const APPROVED  = 2; // Confirmée
    const REJECTED  = 3; // Refusée
    const CANCELLED = 4; // Annulée

    /**
     * Statuts considérés comme « actifs » :
     *  - une résa avec un de ces statuts bloque un créneau (busy)
     *  - et reste visible sur le calendrier / les listings
     *
     * Les statuts REJECTED et CANCELLED sont exclus : la résa n'occupe plus
     * la ressource et ne doit plus apparaître comme conflit.
     */
    public const ACTIVE_STATUSES = [self::PENDING, self::APPROVED];

    #[ORM\Id]
    #[ORM\Column(type: 'smallint', options: ['unsigned' => true])]
    private ?int $id;

    #[ORM\Column(name: 'label', length: 85)]
    private string $label;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }


    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function __toString(): string
    {
        return (string)($this->getLabel() ?? sprintf('Statut #%d', $this->getId() ?? 0));
    }
}
