<?php
namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
#[ORM\Entity]
#[ORM\Table(name: 'schedules')]
class Schedule
{
    #[ORM\Id]
    #[ORM\Column( type: 'smallint', options: ['unsigned' => true])]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(name: 'name', length: 85)]
    private string $name;

    #[ORM\Column(name: 'isdefault', type: 'boolean', options: ['default' => 0])]
    private bool $isDefault = false;

    #[ORM\Column(name: 'weekdaystart', type: 'smallint', options: ['unsigned' => true])]
    private int $weekdayStart = 1;

    #[ORM\Column(name: 'daysvisible', type: 'smallint', options: ['unsigned' => true])]
    private int $daysVisible = 7;

    #[ORM\ManyToOne(inversedBy: 'schedules')]
    #[ORM\JoinColumn(name: 'layout_id', nullable: true, onDelete: 'SET NULL')]
    private ?Layout $layout = null;

    #[ORM\Column(name: 'notes', type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(name: 'published', type: 'boolean', options: ['default' => 0])]
    private bool $published = false;
    #[ORM\Column(name: 'public_id', length: 50, nullable: true, unique: true)]
    private ?string $publicId = null;

    #[ORM\Column(name: 'allow_calendar_subscription', type: 'boolean', options: ['default' => 0])]
    private bool $allowCalendarSubscription = false;

    #[ORM\Column(name: 'start_date', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(name: 'end_date', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column(name: 'allow_concurrent_bookings', type: 'boolean', options: ['default' => 0])]
    private bool $allowConcurrentBookings = false;

    #[ORM\Column(name: 'default_layout', type: 'boolean', options: ['default' => 0])]
    private bool $defaultLayout = false;

    #[ORM\Column(name: 'total_concurrent_reservations', type: 'smallint', nullable: true, options: ['unsigned' => true])]
    private ?int $totalConcurrentReservations = null;

    #[ORM\Column(name: 'max_resources_per_reservation', type: 'smallint', nullable: true, options: ['unsigned' => true])]
    private ?int $maxResourcesPerReservation = null;

    #[ORM\Column(name: 'additional_properties', type: 'json', nullable: true)]
    private ?array $additionalProperties = null;
    #[ORM\OneToMany(mappedBy: 'schedule', targetEntity: Resource::class)]
    private Collection $resources;



    public function __construct()
    {
        $this->resources = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): self
    {
        $this->isDefault = $isDefault;
        return $this;
    }

    public function getWeekdayStart(): int
    {
        return $this->weekdayStart;
    }

    public function setWeekdayStart(int $weekdayStart): self
    {
        $this->weekdayStart = $weekdayStart;
        return $this;
    }

    public function getDaysVisible(): int
    {
        return $this->daysVisible;
    }

    public function setDaysVisible(int $daysVisible): self
    {
        $this->daysVisible = $daysVisible;
        return $this;
    }

    public function getLayout(): ?Layout
    {
        return $this->layout;
    }

    public function setLayout(?Layout $layout): self
    {
        $this->layout = $layout;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function setPublished(bool $published): self
    {
        $this->published = $published;
        return $this;
    }

    public function getPublicId(): ?string
    {
        return $this->publicId;
    }

    public function setPublicId(?string $publicId): self
    {
        $this->publicId = $publicId;
        return $this;
    }

    public function isAllowCalendarSubscription(): bool
    {
        return $this->allowCalendarSubscription;
    }

    public function setAllowCalendarSubscription(bool $allowCalendarSubscription): self
    {
        $this->allowCalendarSubscription = $allowCalendarSubscription;
        return $this;
    }

    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeImmutable $startDate): self
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeImmutable $endDate): self
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function isAllowConcurrentBookings(): bool
    {
        return $this->allowConcurrentBookings;
    }

    public function setAllowConcurrentBookings(bool $allowConcurrentBookings): self
    {
        $this->allowConcurrentBookings = $allowConcurrentBookings;
        return $this;
    }

    public function isDefaultLayout(): bool
    {
        return $this->defaultLayout;
    }

    public function setDefaultLayout(bool $defaultLayout): self
    {
        $this->defaultLayout = $defaultLayout;
        return $this;
    }

    public function getTotalConcurrentReservations(): ?int
    {
        return $this->totalConcurrentReservations;
    }

    public function setTotalConcurrentReservations(?int $totalConcurrentReservations): self
    {
        $this->totalConcurrentReservations = $totalConcurrentReservations;
        return $this;
    }

    public function getMaxResourcesPerReservation(): ?int
    {
        return $this->maxResourcesPerReservation;
    }

    public function setMaxResourcesPerReservation(?int $maxResourcesPerReservation): self
    {
        $this->maxResourcesPerReservation = $maxResourcesPerReservation;
        return $this;
    }

    public function getAdditionalProperties(): ?array
    {
        return $this->additionalProperties;
    }

    public function setAdditionalProperties(?array $additionalProperties): self
    {
        $this->additionalProperties = $additionalProperties;
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
            $resource->setSchedule($this);
        }

        return $this;
    }

    public function removeResource(Resource $resource): static
    {
        if ($this->resources->removeElement($resource)) {
            // set the owning side to null (unless already changed)
            if ($resource->getSchedule() === $this) {
                $resource->setSchedule(null);
            }
        }

        return $this;
    }
}
