<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'layouts')]
class Layout
{
    public const TYPE_TIMES = 0; // grille à la minute/heure (LibreBooking “times”)
    public const TYPE_PERIODS = 1; // grille par périodes nommées (LibreBooking “periods”)
    #[ORM\Id]
    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(name: 'name', length: 85)]
    private string $name;

    #[ORM\Column(name: 'timezone', length: 85)]
    #[Assert\NotBlank]
    #[Assert\Timezone] // valide “Europe/Paris”, “America/New_York”, etc.
    private string $timezone = 'Europe/Paris';

    #[ORM\Column(name: 'layout_type', type: 'smallint', options: ['unsigned' => true, 'default' => 0])]
    #[Assert\Choice(choices: [self::TYPE_TIMES, self::TYPE_PERIODS])]
    private int $layoutType = self::TYPE_TIMES;

    // Schedules rattachés à ce layout (si tu as bien un Schedule->layout)
    #[ORM\OneToMany(mappedBy: 'layout', targetEntity: Schedule::class)]
    private Collection $schedules;

    #[ORM\OneToMany(mappedBy: 'layout', targetEntity: TimeBlock::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $timeBlocks;

    public function __construct()
    {
        $this->schedules  = new ArrayCollection();
        $this->timeBlocks = new ArrayCollection();
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

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function setTimezone(string $timezone): static
    {
        $this->timezone = $timezone;

        return $this;
    }

    public function getLayoutType(): int
    {
        return $this->layoutType;
    }

    public function setLayoutType(int $layoutType): self
    {
        $this->layoutType = $layoutType;
        return $this;
    }

    /** @return Collection<int, Schedule> */
    public function getSchedules(): Collection
    {
        return $this->schedules;
    }

    public function addSchedule(Schedule $schedule): self
    {
        if (!$this->schedules->contains($schedule)) {
            $this->schedules->add($schedule);
            $schedule->setLayout($this); //
        }
        return $this;
    }

    public function removeSchedule(Schedule $schedule): self
    {
        if ($this->schedules->removeElement($schedule)) {
            if ($schedule->getLayout() === $this) {
                $schedule->setLayout(null); //
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, TimeBlock>
     */
    public function getTimeBlocks(): Collection
    {
        return $this->timeBlocks;
    }

    public function addTimeBlock(TimeBlock $timeBlock): static
    {
        if (!$this->timeBlocks->contains($timeBlock)) {
            $this->timeBlocks->add($timeBlock);
            $timeBlock->setLayout($this);
        }

        return $this;
    }

    public function removeTimeBlock(TimeBlock $timeBlock): static
    {
        if ($this->timeBlocks->removeElement($timeBlock)) {
            // set the owning side to null (unless already changed)
            if ($timeBlock->getLayout() === $this) {
                $timeBlock->setLayout(null);
            }
        }

        return $this;
    }
}
