<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'reservation_types')]
class ReservationType
{
    // IDs stables définis en base (voir migration de seed au déploiement)
    public const STANDARD = 1;

    #[ORM\Id]
    #[ORM\Column(type: 'smallint', options: ['unsigned' => true])]
    private ?int $id;

    #[ORM\Column(name: 'label', length: 85)]
    private string $label;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
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
        // Toujours retourner une string, sans jamais throw
        return (string) ($this->getLabel() ?? sprintf('Type #%d', $this->getId() ?? 0));
    }

}
