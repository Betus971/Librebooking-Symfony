<?php
namespace App\Presta\Entity;

use App\Presta\Repository\PrestataireRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Entity\User;

#[ORM\Entity(repositoryClass: PrestataireRepository::class)]
#[ORM\Table(name: 'presta_prestataire')]
class Prestataire
{
    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    private ?string $prenom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photo = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    /**
     * Fenêtre glissante de réservation : les clients peuvent réserver jusqu'à
     * N jours à l'avance. « Aujourd'hui » avançant chaque jour, la fenêtre glisse
     * automatiquement (un jour se ferme, un nouveau s'ouvre au bout) — aucune
     * intervention quotidienne. Défaut : 14 jours (2 semaines).
     */
    #[ORM\Column(type: 'integer', options: ['default' => 14])]
    private int $horizonJours = 14;

    /**
     * Délai minimum d'annulation côté client, en heures : un client ne peut plus
     * annuler sa réservation à moins de N heures du début du rendez-vous.
     * 0 = pas de délai (annulation possible jusqu'au dernier moment).
     * Le prestataire, lui, n'est jamais soumis à ce délai. Défaut : 48 h.
     */
    #[ORM\Column(type: 'integer', options: ['default' => 48])]
    private int $delaiAnnulationHeures = 48;

    /**
     * Si vrai : un client ne peut avoir qu'UN SEUL rendez-vous individuel actif
     * (en attente ou confirmé, non passé) chez CE prestataire à la fois. Il doit
     * annuler ou attendre que son RDV soit passé avant d'en reprendre un. Ne
     * concerne pas les séances de groupe. Défaut : désactivé.
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $unRdvActifParClient = false;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $icalToken = null;

    /**
     * @var Collection<int, Service>
     */
    #[ORM\OneToMany(mappedBy: 'prestataire', targetEntity: Service::class, orphanRemoval: true)]
    private Collection $services;

    public function __construct()
    {
        $this->services = new ArrayCollection();
    }

    public function getHorizonJours(): int
    {
        return $this->horizonJours > 0 ? $this->horizonJours : 14;
    }

    public function setHorizonJours(int $horizonJours): static
    {
        $this->horizonJours = $horizonJours;
        return $this;
    }

    /**
     * Dernière date/heure réservable = fin de journée à aujourd'hui + horizon.
     * Au-delà, plus aucun créneau n'est proposé ni acceptable.
     */
    public function getMaxBookingDate(): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('today', new \DateTimeZone('Europe/Paris')))
            ->modify('+' . $this->getHorizonJours() . ' days')
            ->setTime(23, 59, 59);
    }

    public function getDelaiAnnulationHeures(): int
    {
        return max(0, $this->delaiAnnulationHeures);
    }

    public function setDelaiAnnulationHeures(int $delaiAnnulationHeures): static
    {
        $this->delaiAnnulationHeures = max(0, $delaiAnnulationHeures);
        return $this;
    }

    public function isUnRdvActifParClient(): bool
    {
        return $this->unRdvActifParClient;
    }

    public function setUnRdvActifParClient(bool $unRdvActifParClient): static
    {
        $this->unRdvActifParClient = $unRdvActifParClient;
        return $this;
    }

    /**
     * Un client peut-il encore annuler une réservation qui débute à $debut ?
     * Faux si l'on est à moins du délai d'annulation (en heures) du début.
     * Toujours vrai si le délai est 0.
     */
    public function annulationClientPossible(\DateTimeInterface $debut, ?\DateTimeInterface $maintenant = null): bool
    {
        $delai = $this->getDelaiAnnulationHeures();
        if ($delai <= 0) {
            return true;
        }
        $now = $maintenant ?? new \DateTime('now', new \DateTimeZone('Europe/Paris'));
        $limite = \DateTime::createFromInterface($debut)->modify('-' . $delai . ' hours');

        return $now < $limite;
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

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;
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

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): static
    {
        $this->photo = $photo;
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


    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return Collection<int, Service>
     */
    public function getServices(): Collection
    {
        return $this->services;
    }

    public function addService(Service $service): static
    {
        if (!$this->services->contains($service)) {
            $this->services->add($service);
            $service->setPrestataire($this);
        }

        return $this;
    }

    public function removeService(Service $service): static
    {
        if ($this->services->removeElement($service)) {
            if ($service->getPrestataire() === $this) {
                $service->setPrestataire(null);
            }
        }

        return $this;
    }

    public function getIcalToken(): ?string
    {
        return $this->icalToken;
    }

    public function setIcalToken(?string $icalToken): static
    {
        $this->icalToken = $icalToken;
        return $this;
    }

    public function __toString(): string
    {
        return trim(($this->prenom ?? '').' '.($this->nom ?? ''));
    }
}
