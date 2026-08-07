<?php

namespace App\Entity;

use App\Repository\EquipementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Référentiel géré d'équipements (Wifi, Vidéoprojecteur, Tableau blanc…).
 *
 * Remplace la saisie libre (Resource.notes séparés par des virgules) qui
 * produisait des doublons (« Wifi » / « wifi » / « rétroprojecteur » vs
 * « vidéoprojecteur »). Ici la liste est gérée par l'admin, les ressources
 * ne font que cocher → plus de doublons, filtres propres.
 */
#[ORM\Entity(repositoryClass: EquipementRepository::class)]
class Equipement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80, unique: true)]
    private ?string $nom = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $actif = true;

    /**
     * @var Collection<int, Resource>
     */
    #[ORM\ManyToMany(targetEntity: Resource::class, mappedBy: 'equipements')]
    private Collection $resources;

    public function __construct()
    {
        $this->resources = new ArrayCollection();
    }

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

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;

        return $this;
    }

    /**
     * @return Collection<int, Resource>
     */
    public function getResources(): Collection
    {
        return $this->resources;
    }

    public function __toString(): string
    {
        return (string) $this->nom;
    }
}
