<?php

namespace App\Entity;

use App\Repository\AccessoireRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Accessoire = matériel mobile en stock, DEMANDÉ au moment d'une réservation
 * (micros, pupitres, rallonges, chargeurs…).
 *
 * À ne pas confondre avec {@see Equipement} : Equipement décrit une
 * caractéristique FIXE d'une salle (Wifi, vidéoprojecteur), cochée par l'admin
 * et sans quantité. Ici on gère un STOCK : chaque accessoire a une quantité
 * disponible (ou illimitée), et l'usager en demande N exemplaires par
 * réservation. Le gestionnaire voit ainsi le matériel à préparer.
 */
#[ORM\Entity(repositoryClass: AccessoireRepository::class)]
class Accessoire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    private ?string $nom = null;

    /**
     * Quantité totale en stock. NULL = illimité (∞).
     */
    #[ORM\Column(type: 'integer', nullable: true, options: ['unsigned' => true])]
    private ?int $quantiteDisponible = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $actif = true;

    /**
     * Ressources auxquelles l'accessoire est rattaché. VIDE = disponible pour
     * TOUTES les ressources (affiché « Tout »), comme dans LibreBooking.
     *
     * @var Collection<int, Resource>
     */
    #[ORM\ManyToMany(targetEntity: Resource::class)]
    #[ORM\JoinTable(name: 'accessoire_resource')]
    private Collection $resources;

    /**
     * @var Collection<int, ReservationAccessoire>
     */
    #[ORM\OneToMany(mappedBy: 'accessoire', targetEntity: ReservationAccessoire::class)]
    private Collection $reservationAccessoires;

    public function __construct()
    {
        $this->resources = new ArrayCollection();
        $this->reservationAccessoires = new ArrayCollection();
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

    public function getQuantiteDisponible(): ?int
    {
        return $this->quantiteDisponible;
    }

    public function setQuantiteDisponible(?int $quantiteDisponible): static
    {
        $this->quantiteDisponible = $quantiteDisponible;

        return $this;
    }

    /** Stock illimité (aucune quantité maximale fixée). */
    public function isIllimite(): bool
    {
        return $this->quantiteDisponible === null;
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

    public function addResource(Resource $resource): static
    {
        if (!$this->resources->contains($resource)) {
            $this->resources->add($resource);
        }

        return $this;
    }

    public function removeResource(Resource $resource): static
    {
        $this->resources->removeElement($resource);

        return $this;
    }

    /** Aucune ressource rattachée = disponible pour toutes (« Tout »). */
    public function isDisponiblePourToutes(): bool
    {
        return $this->resources->isEmpty();
    }

    /** L'accessoire est-il proposable pour cette ressource ? */
    public function estDisponiblePour(Resource $resource): bool
    {
        return $this->resources->isEmpty() || $this->resources->contains($resource);
    }

    /**
     * @return Collection<int, ReservationAccessoire>
     */
    public function getReservationAccessoires(): Collection
    {
        return $this->reservationAccessoires;
    }

    public function __toString(): string
    {
        return (string) $this->nom;
    }
}
