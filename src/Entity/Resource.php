<?php
namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use App\Repository\ResourceRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
#[ORM\Entity (repositoryClass: ResourceRepository::class)]
#[ORM\Table(name: 'resources')]
class Resource
{
    #[ORM\Id]
    #[ORM\Column(type: 'smallint', options: ['unsigned' => true])]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'resources')]
    #[ORM\JoinColumn(name: 'schedule_id', nullable: false, onDelete: 'CASCADE')]
    private Schedule $schedule;

    #[ORM\Column(name: 'name', length: 85)]
    private string $name;

    #[ORM\Column(name: 'isactive', type: 'boolean', options: ['default' => 1])]
    private bool $isActive = true;

    #[ORM\Column(name: 'requires_approval', type: 'boolean', options: ['default' => 0])]
    private bool $requiresApproval = false;

    #[ORM\Column(name: 'allow_multiday_reservations', type: 'boolean', options: ['default' => 0])]
    private bool $allowMultiday = false;

    #[ORM\Column(name: 'unit_cost', type: 'decimal', precision: 7, scale: 2, nullable: true)]
    private ?string $unitCost = null;

    #[ORM\Column(name: 'min_duration', type: 'integer', nullable: true)]
    private ?int $minDuration = null;

    #[ORM\Column(name: 'max_duration', type: 'integer', nullable: true)]
    private ?int $maxDuration = null;

    #[ORM\Column(name: 'location', type: Types::STRING, length: 255, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(name: 'contact_info', type: Types::STRING, length: 255, nullable: true)]
    private ?string $contactInfo = null;

    #[ORM\Column(name: 'description', type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'notes', type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(name: 'min_increment', type: Types::INTEGER, nullable: true)]
    private ?int $minIncrement = null;

    #[ORM\Column(name: 'autoassign', type: Types::BOOLEAN, options: ['default' => 1])]
    private bool $autoassign = true;

    #[ORM\Column(name: 'max_participants', type: Types::INTEGER, nullable: true, options: ['unsigned' => true])]
    private ?int $maxParticipants = null; // équivalent mediumint UNSIGNED (on reste en int Doctrine)

    #[ORM\Column(name: 'min_notice_time_add', type: Types::INTEGER, nullable: true)]
    private ?int $minNoticeTimeAdd = null;

    #[ORM\Column(name: 'max_notice_time', type: Types::INTEGER, nullable: true)]
    private ?int $maxNoticeTime = null;

    #[ORM\Column(name: 'image_name', type: Types::STRING, length: 255, nullable: true)]
    private ?string $imageName = null;

    #[ORM\Column(name: 'legacyid', type: Types::STRING, length: 16, nullable: true)]
    private ?string $legacyId = null;

    #[ORM\Column(name: 'public_id', type: Types::STRING, length: 20, nullable: true, unique: true)]
    private ?string $publicId = null;

    #[ORM\Column(name: 'allow_calendar_subscription', type: Types::BOOLEAN, options: ['default' => 0])]
    private bool $allowCalendarSubscription = false;

    #[ORM\Column(name: 'sort_order', type: Types::INTEGER, nullable: true, options: ['unsigned' => true])]
    private ?int $sortOrder = null;

    #[ORM\Column(name: 'status_id', type: Types::INTEGER, options: ['unsigned' => true, 'default' => 1])]
    private int $statusId = 1;

    #[ORM\Column(name: 'buffer_time', type: Types::INTEGER, nullable: true, options: ['unsigned' => true])]
    private ?int $bufferTime = null;

    #[ORM\Column(name: 'enable_check_in', type: Types::BOOLEAN, options: ['default' => 0])]
    private bool $enableCheckIn = false;

    #[ORM\Column(name: 'auto_release_minutes', type: Types::INTEGER, nullable: true, options: ['unsigned' => true])]
    private ?int $autoReleaseMinutes = null;

    #[ORM\Column(name: 'color', type: Types::STRING, length: 10, nullable: true)]
    private ?string $color = null;

    #[ORM\Column(name: 'allow_display', type: Types::BOOLEAN, options: ['default' => 0])]
    private bool $allowDisplay = false;

    #[ORM\Column(name: 'credit_count', type: Types::DECIMAL, precision: 7, scale: 2, nullable: true)]
    private ?string $creditCount = null;

    #[ORM\Column(name: 'peak_credit_count', type: Types::DECIMAL, precision: 7, scale: 2, nullable: true)]
    private ?string $peakCreditCount = null;

    #[ORM\Column(name: 'min_notice_time_update', type: Types::INTEGER, nullable: true)]
    private ?int $minNoticeTimeUpdate = null;

    #[ORM\Column(name: 'min_notice_time_delete', type: Types::INTEGER, nullable: true)]
    private ?int $minNoticeTimeDelete = null;

    #[ORM\Column(name: 'date_created', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateCreated = null;

    #[ORM\Column(name: 'last_modified', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastModified = null;

    #[ORM\Column(name: 'additional_properties', type: Types::TEXT, nullable: true)]
    private ?string $additionalProperties = null;

    // --- FKs futures (option A: en scalar pour compiler, option B: en relations -> Étape B)

    #[ORM\Column(name: 'resource_type_id', type: Types::INTEGER, nullable: true, options: ['unsigned' => true])]
    private ?int $resourceTypeId = null;

    #[ORM\Column(name: 'resource_status_reason_id', type: Types::INTEGER, nullable: true, options: ['unsigned' => true])]
    private ?int $resourceStatusReasonId = null;

    #[ORM\ManyToOne(inversedBy: 'resources')]
    private ?ResourceCategory $category = null;

    #[ORM\ManyToOne(targetEntity: ResourceGroup::class, inversedBy: 'resources')]
    #[ORM\JoinColumn(name: 'admin_group_id', referencedColumnName: 'id')]
    private ?ResourceGroup $resourceGroup = null;




    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function isRequiresApproval(): ?bool
    {
        return $this->requiresApproval;
    }

    public function setRequiresApproval(bool $requiresApproval): static
    {
        $this->requiresApproval = $requiresApproval;

        return $this;
    }

    public function isAllowMultiday(): ?bool
    {
        return $this->allowMultiday;
    }

    public function setAllowMultiday(bool $allowMultiday): static
    {
        $this->allowMultiday = $allowMultiday;

        return $this;
    }

    public function getUnitCost(): ?string
    {
        return $this->unitCost;
    }

    public function setUnitCost(?string $unitCost): static
    {
        $this->unitCost = $unitCost;

        return $this;
    }

    public function getMinDuration(): ?int
    {
        return $this->minDuration;
    }

    public function setMinDuration(?int $minDuration): static
    {
        $this->minDuration = $minDuration;

        return $this;
    }

    public function getMaxDuration(): ?int
    {
        return $this->maxDuration;
    }

    public function setMaxDuration(?int $maxDuration): static
    {
        $this->maxDuration = $maxDuration;

        return $this;
    }

    public function getSchedule(): ?Schedule
    {
        return $this->schedule;
    }

    public function setSchedule(?Schedule $schedule): static
    {
        $this->schedule = $schedule;

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getContactInfo(): ?string
    {
        return $this->contactInfo;
    }

    public function setContactInfo(?string $contactInfo): static
    {
        $this->contactInfo = $contactInfo;

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

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getMinIncrement(): ?int
    {
        return $this->minIncrement;
    }

    public function setMinIncrement(?int $minIncrement): static
    {
        $this->minIncrement = $minIncrement;

        return $this;
    }

    public function isAutoassign(): ?bool
    {
        return $this->autoassign;
    }

    public function setAutoassign(bool $autoassign): static
    {
        $this->autoassign = $autoassign;

        return $this;
    }

    public function getMaxParticipants(): ?int
    {
        return $this->maxParticipants;
    }

    public function setMaxParticipants(?int $maxParticipants): static
    {
        $this->maxParticipants = $maxParticipants;

        return $this;
    }

    public function getMinNoticeTimeAdd(): ?int
    {
        return $this->minNoticeTimeAdd;
    }

    public function setMinNoticeTimeAdd(?int $minNoticeTimeAdd): static
    {
        $this->minNoticeTimeAdd = $minNoticeTimeAdd;

        return $this;
    }

    public function getMaxNoticeTime(): ?int
    {
        return $this->maxNoticeTime;
    }

    public function setMaxNoticeTime(?int $maxNoticeTime): static
    {
        $this->maxNoticeTime = $maxNoticeTime;

        return $this;
    }

    public function getImageName(): ?string
    {
        return $this->imageName;
    }

    public function setImageName(?string $imageName): static
    {
        $this->imageName = $imageName;
        return $this;
    }

    public function getLegacyId(): ?string
    {
        return $this->legacyId;
    }

    public function setLegacyId(?string $legacyId): static
    {
        $this->legacyId = $legacyId;

        return $this;
    }

    public function getPublicId(): ?string
    {
        return $this->publicId;
    }

    public function setPublicId(?string $publicId): static
    {
        $this->publicId = $publicId;

        return $this;
    }

    public function isAllowCalendarSubscription(): ?bool
    {
        return $this->allowCalendarSubscription;
    }

    public function setAllowCalendarSubscription(bool $allowCalendarSubscription): static
    {
        $this->allowCalendarSubscription = $allowCalendarSubscription;

        return $this;
    }

    public function getSortOrder(): ?int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(?int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function getStatusId(): ?int
    {
        return $this->statusId;
    }

    public function setStatusId(int $statusId): static
    {
        $this->statusId = $statusId;

        return $this;
    }

    public function getBufferTime(): ?int
    {
        return $this->bufferTime;
    }

    public function setBufferTime(?int $bufferTime): static
    {
        $this->bufferTime = $bufferTime;

        return $this;
    }

    public function isEnableCheckIn(): ?bool
    {
        return $this->enableCheckIn;
    }

    public function setEnableCheckIn(bool $enableCheckIn): static
    {
        $this->enableCheckIn = $enableCheckIn;

        return $this;
    }

    public function getAutoReleaseMinutes(): ?int
    {
        return $this->autoReleaseMinutes;
    }

    public function setAutoReleaseMinutes(?int $autoReleaseMinutes): static
    {
        $this->autoReleaseMinutes = $autoReleaseMinutes;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function isAllowDisplay(): ?bool
    {
        return $this->allowDisplay;
    }

    public function setAllowDisplay(bool $allowDisplay): static
    {
        $this->allowDisplay = $allowDisplay;

        return $this;
    }

    public function getCreditCount(): ?string
    {
        return $this->creditCount;
    }

    public function setCreditCount(?string $creditCount): static
    {
        $this->creditCount = $creditCount;

        return $this;
    }

    public function getPeakCreditCount(): ?string
    {
        return $this->peakCreditCount;
    }

    public function setPeakCreditCount(?string $peakCreditCount): static
    {
        $this->peakCreditCount = $peakCreditCount;

        return $this;
    }

    public function getMinNoticeTimeUpdate(): ?int
    {
        return $this->minNoticeTimeUpdate;
    }

    public function setMinNoticeTimeUpdate(?int $minNoticeTimeUpdate): static
    {
        $this->minNoticeTimeUpdate = $minNoticeTimeUpdate;

        return $this;
    }

    public function getMinNoticeTimeDelete(): ?int
    {
        return $this->minNoticeTimeDelete;
    }

    public function setMinNoticeTimeDelete(?int $minNoticeTimeDelete): static
    {
        $this->minNoticeTimeDelete = $minNoticeTimeDelete;

        return $this;
    }

    public function getDateCreated(): ?\DateTime
    {
        return $this->dateCreated;
    }

    public function setDateCreated(?\DateTime $dateCreated): static
    {
        $this->dateCreated = $dateCreated;

        return $this;
    }

    public function getLastModified(): ?\DateTime
    {
        return $this->lastModified;
    }

    public function setLastModified(?\DateTime $lastModified): static
    {
        $this->lastModified = $lastModified;

        return $this;
    }

    public function getAdditionalProperties(): ?string
    {
        return $this->additionalProperties;
    }

    public function setAdditionalProperties(?string $additionalProperties): static
    {
        $this->additionalProperties = $additionalProperties;

        return $this;
    }

    public function getResourceTypeId(): ?int
    {
        return $this->resourceTypeId;
    }

    public function setResourceTypeId(?int $resourceTypeId): static
    {
        $this->resourceTypeId = $resourceTypeId;

        return $this;
    }

    public function getResourceStatusReasonId(): ?int
    {
        return $this->resourceStatusReasonId;
    }

    public function setResourceStatusReasonId(?int $resourceStatusReasonId): static
    {
        $this->resourceStatusReasonId = $resourceStatusReasonId;

        return $this;
    }

    public function getCategory(): ?ResourceCategory
    {
        return $this->category;
    }

    public function setCategory(?ResourceCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function __toString(): string
    {
        return (string) $this->getName();
    }

    public function getResourceGroup(): ?ResourceGroup
    {
        return $this->resourceGroup;
    }

    public function setResourceGroup(?ResourceGroup $resourceGroup): static
    {
        $this->resourceGroup = $resourceGroup;

        return $this;
    }



}
