<?php

namespace App\Entity;

use App\Repository\TimeBlockRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: TimeBlockRepository::class)]
#[ORM\Table(name: 'time_blocks')]
#[ORM\Index(name: 'idx_tb_layout', columns: ['layout_id'])]
#[ORM\Index(name: 'idx_tb_layout_dow', columns: ['layout_id','day_of_week'])]
class TimeBlock
{
    public const OPEN   = 1;
    public const CLOSED = 0;

    #[ORM\Id]
    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    #[ORM\GeneratedValue]
    private ?int $id = null;


    // v3 — facultatifs (nullable) pour rester rétro-compatibles
    #[ORM\Column(length: 85, nullable: true)]
    private ?string $label = null;

    #[ORM\Column(name: 'end_label', length: 85, nullable: true)]
    private ?string $endLabel = null;

    // 0..6 (0=Dimanche) — nullable: si null => s’applique à tous les jours
    #[ORM\Column(name: 'day_of_week', type: 'smallint', nullable: true, options: ['unsigned' => true])]
    #[Assert\Choice(choices: [null, 0, 1, 2, 3, 4, 5, 6], message: 'Le jour de la semaine doit être entre 0 (dimanche) et 6 (samedi), ou NULL pour tous les jours.')]
    private ?int $dayOfWeek = null;

    // Présents dans ta base
    #[ORM\Column(name: 'availability_code', type: 'smallint', options: ['unsigned' => true])]
    #[Assert\Choice(choices: [self::OPEN, self::CLOSED])]
    private int $availabilityCode = self::OPEN;

    // Utilise TIME_IMMUTABLE pour coller à ton schéma
    #[ORM\Column(name: 'start_time', type: Types::TIME_IMMUTABLE)]
    private \DateTimeInterface $startTime;

    #[ORM\Column(name: 'end_time', type: Types::TIME_IMMUTABLE)]
    private \DateTimeInterface $endTime;


    #[ORM\ManyToOne(inversedBy: 'timeBlocks')]
    #[ORM\JoinColumn(name: 'layout_id', nullable: false, onDelete: 'CASCADE')]
    private ?Layout $layout = null;


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;
        return $this;
    }

    public function getEndLabel(): ?string
    {
        return $this->endLabel;
    }

    public function setEndLabel(?string $endLabel): self
    {
        $this->endLabel = $endLabel;
        return $this;
    }

    public function getDayOfWeek(): ?int
    {
        return $this->dayOfWeek;
    }

    public function setDayOfWeek(?int $dayOfWeek): self
    {
        $this->dayOfWeek = $dayOfWeek;
        return $this;
    }

    public function getAvailabilityCode(): int
    {
        return $this->availabilityCode;
    }

    public function setAvailabilityCode(int $availabilityCode): self
    {
        $this->availabilityCode = $availabilityCode;
        return $this;
    }

    public function getStartTime(): \DateTimeInterface
    {
        return $this->startTime;
    }

    public function setStartTime(\DateTimeInterface $startTime): self
    {
        $this->startTime = $startTime;
        return $this;
    }

    public function getEndTime(): \DateTimeInterface
    {
        return $this->endTime;
    }

    public function setEndTime(\DateTimeInterface $endTime): self
    {
        $this->endTime = $endTime;
        return $this;
    }

    public function getLayout(): ?Layout
    {
        return $this->layout;
    }

    public function setLayout(?Layout $layout): static
    {
        $this->layout = $layout;

        return $this;
    }


    public function isOpen(): bool
    {
        return $this->availabilityCode === self::OPEN;
    }

    public function getStartMinuteOfDay(): int
    {
        return ((int)$this->startTime->format('H')) * 60 + (int)$this->startTime->format('i');
    }

    public function getEndMinuteOfDay(): int
    {
        return ((int)$this->endTime->format('H')) * 60 + (int)$this->endTime->format('i');
    }
    #[Assert\Callback]
    public function validateTimes(ExecutionContextInterface $context): void
    {
        if (isset($this->startTime, $this->endTime)) {
            if ($this->endTime <= $this->startTime) {
                $context->buildViolation("L'heure de fin doit être après l'heure de début.")
                    ->atPath('endTime')
                    ->addViolation();
            }
        }
    }
}
