<?php

namespace App\Entity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
#[ORM\Table(name: 'blackout_series')]
class BlackoutSeries
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column( type: 'integer', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(name: 'date_created', type: 'datetime')]
    private \DateTimeInterface $dateCreated;

    #[ORM\Column(name: 'last_modified', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastModified = null;

    #[ORM\Column(name: 'title', length: 85)]
    private string $title;

    #[ORM\Column(name: 'description', type: 'text', nullable: true)]
    private ?string $description = null;

    // LibreBooking -> users.user_id
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'owner_id', nullable: false, onDelete: 'CASCADE')]
    private User $owner;

    // Variante présente dans ton create-schema : un seul resource_id sur la série
    #[ORM\ManyToOne(targetEntity: Resource::class)]
    #[ORM\JoinColumn(name: 'resource_id', nullable: false)]
    private Resource $resource;

    #[ORM\Column(name: 'legacyid', length: 16, nullable: true)]
    private ?string $legacyId = null;

    /** @var Collection<int,BlackoutInstance> */
    #[ORM\OneToMany(mappedBy: 'series', targetEntity: BlackoutInstance::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $instances;

    public function __construct()
    {
        $this->dateCreated = new \DateTimeImmutable();
        $this->instances = new ArrayCollection();
    }

    // --- Getters/Setters ---
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateCreated(): \DateTimeInterface
    {
        return $this->dateCreated;
    }

    public function setDateCreated(\DateTimeInterface $d): self
    {
        $this->dateCreated = $d;
        return $this;
    }

    public function getLastModified(): ?\DateTimeInterface
    {
        return $this->lastModified;
    }

    public function setLastModified(?\DateTimeInterface $d): self
    {
        $this->lastModified = $d;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $t): self
    {
        $this->title = $t;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $d): self
    {
        $this->description = $d;
        return $this;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function setOwner(User $o): self
    {
        $this->owner = $o;
        return $this;
    }

    public function getResource(): Resource
    {
        return $this->resource;
    }

    public function setResource(Resource $r): self
    {
        $this->resource = $r;
        return $this;
    }

    public function getLegacyId(): ?string
    {
        return $this->legacyId;
    }

    public function setLegacyId(?string $id): self
    {
        $this->legacyId = $id;
        return $this;
    }

    /** @return Collection<int,BlackoutInstance> */
    public function getInstances(): Collection
    {
        return $this->instances;
    }

    public function addInstance(BlackoutInstance $i): self
    {
        if (!$this->instances->contains($i)) {
            $this->instances->add($i);
            $i->setSeries($this);
        }
        return $this;
    }

    public function removeInstance(BlackoutInstance $i): self
    {
        if ($this->instances->removeElement($i) && $i->getSeries() === $this) {
            $i->setSeries(null);
        }
        return $this;
    }

}
