<?php

namespace App\Presta\Entity;

use App\Presta\Repository\PrestaCategorieRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Catégorie de prestation (ex. TIR, CCPM, Coiffure, Autre).
 *
 * Gérée par le super-admin (CRUD dédié). Chaque Service (prestation) se
 * rattache à une catégorie, ce qui permet au client de parcourir les
 * prestations PAR catégorie plutôt que par prestataire.
 */
#[ORM\Entity(repositoryClass: PrestaCategorieRepository::class)]
#[ORM\Table(name: 'presta_categorie')]
class PrestaCategorie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function __toString(): string
    {
        return $this->nom ?? '';
    }
}
