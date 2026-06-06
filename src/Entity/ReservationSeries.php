<?php
namespace App\Entity;

use App\Repository\ReservationSeriesRepository;
use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity (repositoryClass: ReservationSeriesRepository::class)]
#[ORM\Table(name: 'reservation_series')]
class ReservationSeries
{
    #[ORM\Id]
    #[ORM\Column( type: 'integer', options: ['unsigned' => true])]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private ?Uuid $uuid = null;

    #[ORM\Column(name: 'title', length: 85)]
    private string $title;

    #[ORM\Column(name: 'description', type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'owner_id', nullable: false, onDelete: 'CASCADE')]
    private User $owner;

    #[ORM\Column(name: 'legacyid', length: 16, nullable: true)]
    private ?string $legacyid = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'type_id', nullable: false)]
    private ReservationType $type;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'status_id', nullable: false)]
    private ?ReservationStatus $status = null;

    #[ORM\Column(name: 'allow_participation', type: 'boolean', options: ['default' => 0])]
    private bool $allowParticipation = false;

    #[ORM\Column(name: 'allow_anon_participation', type: 'boolean', options: ['default' => 0])]
    private bool $allowAnonParticipation = false;

    #[ORM\Column(name: 'repeat_type', length: 10, nullable: true)]
    private ?string $repeatType = null;

    #[ORM\Column(name: 'repeat_options', length: 255, nullable: true)]
    private ?string $repeatOptions = null;

    #[ORM\Column(name: 'date_created', type: 'datetime_immutable')]
    private \DateTimeInterface $dateCreated;

    #[ORM\Column(name: 'last_modified', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastModified = null;

    #[ORM\OneToMany(mappedBy: 'series', targetEntity: ReservationInstance::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $instances;

    #[ORM\OneToMany(mappedBy: 'series', targetEntity: ReservationResource::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $reservationResources;

    /**
     * @var Collection<int, ReservationAttachment>
     */
    #[ORM\OneToMany(targetEntity: ReservationAttachment::class, mappedBy: 'series')]
    private Collection $reservationAttachments;

    public function __construct()
    {
        $this->uuid = Uuid::v4();
        $this->instances = new ArrayCollection();
        $this->reservationResources = new ArrayCollection();
        $now = new \DateTimeImmutable('now');
        $this->dateCreated = $now;
        $this->lastModified = $now;
        $this->reservationAttachments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): ?Uuid
    {
        return $this->uuid;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

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

    public function isAllowParticipation(): ?bool
    {
        return $this->allowParticipation;
    }

    public function setAllowParticipation(bool $allowParticipation): static
    {
        $this->allowParticipation = $allowParticipation;

        return $this;
    }

    public function isAllowAnonParticipation(): ?bool
    {
        return $this->allowAnonParticipation;
    }

    public function setAllowAnonParticipation(bool $allowAnonParticipation): static
    {
        $this->allowAnonParticipation = $allowAnonParticipation;

        return $this;
    }

    public function getRepeatType(): ?string
    {
        return $this->repeatType;
    }

    public function setRepeatType(?string $repeatType): static
    {
        $this->repeatType = $repeatType;

        return $this;
    }

    public function getRepeatOptions(): ?string
    {
        return $this->repeatOptions;
    }

    public function setRepeatOptions(?string $repeatOptions): static
    {
        $this->repeatOptions = $repeatOptions;

        return $this;
    }

    public function getDateCreated(): ?\DateTimeImmutable
    {
        return $this->dateCreated;
    }

    public function setDateCreated(\DateTimeImmutable $dateCreated): static
    {
        $this->dateCreated = $dateCreated;

        return $this;
    }

    public function getLastModified(): ?\DateTimeImmutable
    {
        return $this->lastModified;
    }

    public function setLastModified(?\DateTimeImmutable $lastModified): static
    {
        $this->lastModified = $lastModified;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function getType(): ?ReservationType
    {
        return $this->type;
    }

    public function setType(?ReservationType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getStatus(): ?ReservationStatus
    {
        return $this->status;
    }

    public function setStatus(?ReservationStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return Collection<int, ReservationInstance>
     */
    public function getInstances(): Collection
    {
        return $this->instances;
    }

    public function addInstance(ReservationInstance $instance): static
    {
        if (!$this->instances->contains($instance)) {
            $this->instances->add($instance);
            $instance->setSeries($this);
        }

        return $this;
    }

    public function removeInstance(ReservationInstance $instance): static
    {
        if ($this->instances->removeElement($instance)) {
            // set the owning side to null (unless already changed)
            if ($instance->getSeries() === $this) {
                $instance->setSeries(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ReservationResource>
     */
    public function getReservationResources(): Collection
    {
        return $this->reservationResources;
    }

    public function addReservationResource(ReservationResource $reservationResource): static
    {
        if (!$this->reservationResources->contains($reservationResource)) {
            $this->reservationResources->add($reservationResource);
            $reservationResource->setSeries($this);
        }

        return $this;
    }

    public function removeReservationResource(ReservationResource $reservationResource): static
    {
        if ($this->reservationResources->removeElement($reservationResource)) {
            // set the owning side to null (unless already changed)
            if ($reservationResource->getSeries() === $this) {
                $reservationResource->setSeries(null);
            }
        }

        return $this;
    }

    public function getNextReservation(): ?ReservationInstance
    {
        $now = new \DateTime();
        $bestCandidate = null;

        // CORRECTION : On utilise bien $this->instances
        foreach ($this->instances as $instance) {
            // Adapte 'getStartDate' si ton getter s'appelle autrement (ex: getDateStart)
            // On suppose que ton entité ReservationInstance a une méthode getStartDate()
            $date = $instance->getStartDate();

            if ($date > $now) {
                // Si on n'a pas encore de candidat OU si cette date est plus proche que le candidat actuel
                if ($bestCandidate === null || $date < $bestCandidate->getStartDate()) {
                    $bestCandidate = $instance;
                }
            }
        }

        return $bestCandidate;
    }

    /**
     * @return Collection<int, ReservationAttachment>
     */
    public function getReservationAttachments(): Collection
    {
        return $this->reservationAttachments;
    }

    public function addReservationAttachment(ReservationAttachment $reservationAttachment): static
    {
        if (!$this->reservationAttachments->contains($reservationAttachment)) {
            $this->reservationAttachments->add($reservationAttachment);
            $reservationAttachment->setSeries($this);
        }

        return $this;
    }

    public function removeReservationAttachment(ReservationAttachment $reservationAttachment): static
    {
        if ($this->reservationAttachments->removeElement($reservationAttachment)) {
            // set the owning side to null (unless already changed)
            if ($reservationAttachment->getSeries() === $this) {
                $reservationAttachment->setSeries(null);
            }
        }

        return $this;
    }

    public function getLegacyid(): ?string
    {
        return $this->legacyid;
    }

    public function setLegacyid(?string $legacyid): static
    {
        $this->legacyid = $legacyid;

        return $this;
    }

}
