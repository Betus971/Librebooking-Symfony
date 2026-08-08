<?php
namespace App\Presta\Entity;

use App\Presta\Repository\ServiceRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: ServiceRepository::class)]
#[ORM\Table(name: 'presta_service')]
class Service
{
    public const TYPE_INDIVIDUEL = 'INDIVIDUEL';
    public const TYPE_GROUPE = 'GROUPE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'services')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Prestataire $prestataire = null;

    #[ORM\Column(length: 255)]
    private ?string $libelle = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private ?int $dureeMinutes = 30;

    #[ORM\Column(length: 25)]
    private ?string $type = self::TYPE_INDIVIDUEL;

    #[ORM\Column]
    private ?int $capaciteMax = 1;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    /**
     * Approbation requise : si vrai, une réservation de cette prestation est
     * créée « en attente » (PENDING) et doit être validée par le prestataire
     * avant d'être confirmée. Sinon, la réservation est confirmée immédiatement.
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $requiresApproval = false;

    /**
     * Couleur d'affichage de la prestation dans l'agenda (hex, ex. « #6E445A »),
     * choisie par le prestataire dans la palette DSFR. NULL = couleur par défaut.
     */
    #[ORM\Column(length: 7, nullable: true)]
    private ?string $couleur = null;

    // Catégorie (TIR, CCPM, Coiffure…) créée par le super-admin. Nullable pour
    // les prestations existantes ; on rend le champ requis côté formulaire.
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?PrestaCategorie $categorie = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPrestataire(): ?Prestataire
    {
        return $this->prestataire;
    }

    public function setPrestataire(?Prestataire $prestataire): static
    {
        $this->prestataire = $prestataire;
        return $this;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;
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

    public function getDureeMinutes(): ?int
    {
        return $this->dureeMinutes;
    }

    public function setDureeMinutes(int $dureeMinutes): static
    {
        $this->dureeMinutes = $dureeMinutes;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        if (!in_array($type, [self::TYPE_INDIVIDUEL, self::TYPE_GROUPE])) {
            throw new \InvalidArgumentException("Invalid type");
        }
        $this->type = $type;
        return $this;
    }

    public function getCapaciteMax(): ?int
    {
        return $this->capaciteMax;
    }

    public function setCapaciteMax(int $capaciteMax): static
    {
        $this->capaciteMax = $capaciteMax;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getCategorie(): ?PrestaCategorie
    {
        return $this->categorie;
    }

    public function setCategorie(?PrestaCategorie $categorie): static
    {
        $this->categorie = $categorie;
        return $this;
    }

    public function isRequiresApproval(): bool
    {
        return $this->requiresApproval;
    }

    public function setRequiresApproval(bool $requiresApproval): static
    {
        $this->requiresApproval = $requiresApproval;
        return $this;
    }

    public function getCouleur(): ?string
    {
        return $this->couleur;
    }

    public function setCouleur(?string $couleur): static
    {
        $this->couleur = $couleur;
        return $this;
    }

    /** Couleur d'affichage effective (couleur choisie ou défaut Bleu France). */
    public function getCouleurAffichage(): string
    {
        return $this->couleur ?: '#000091';
    }

    public function __toString(): string
    {
        return $this->libelle ?? '';
    }
}
