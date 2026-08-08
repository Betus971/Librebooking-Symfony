<?php
namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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

    /**
     * Retourne un résumé des plages d'ouverture du layout, prêt à être affiché
     * dans un widget « Horaires d'ouverture » (cf. demande MOA juin 2026 :
     * afficher cette info sur la fiche ressource ET dans le formulaire de
     * réservation).
     *
     * Format de retour :
     * [
     *   'minTime'   => '08:00',         // heure d'ouverture la plus précoce
     *   'maxTime'   => '17:00',         // heure de fermeture la plus tardive
     *   'daysOpen'  => [1,2,3,4,5],     // jours de la semaine ouverts (1=Lun .. 0=Dim)
     *   'daysLabel' => 'Lundi à vendredi',
     * ]
     *
     * Retourne `null` si aucun créneau d'ouverture n'est défini.
     */
    public function getOpeningSummary(): ?array
    {
        $openBlocks = [];
        foreach ($this->timeBlocks as $tb) {
            if ($tb->isOpen()) {
                $openBlocks[] = $tb;
            }
        }
        if ([] === $openBlocks) {
            return null;
        }

        // Min / max sur l'union des plages
        $minTime = null;
        $maxTime = null;
        // Set des jours couverts. dayOfWeek=null signifie "tous les jours".
        $daysOpen = [];

        foreach ($openBlocks as $tb) {
            $s = $tb->getStartTime()->format('H:i');
            $e = $tb->getEndTime()->format('H:i');

            if (null === $minTime || $s < $minTime) {
                $minTime = $s;
            }
            if (null === $maxTime || $e > $maxTime) {
                $maxTime = $e;
            }

            $dow = $tb->getDayOfWeek();
            if (null === $dow) {
                $daysOpen = [0, 1, 2, 3, 4, 5, 6];
                break; // pas la peine de continuer, "tous les jours" l'emporte
            }
            $daysOpen[] = (int) $dow;
        }
        $daysOpen = array_values(array_unique($daysOpen));
        sort($daysOpen);

        return [
            'minTime'   => $minTime,
            'maxTime'   => $maxTime,
            'daysOpen'  => $daysOpen,
            'daysLabel' => self::formatDaysLabel($daysOpen),
        ];
    }

    /**
     * Convertit un tableau de jours (0=Dim..6=Sam) en label français lisible.
     * Détecte les patterns courants pour un rendu naturel :
     *   - [0..6]      → "Tous les jours"
     *   - [1..5]      → "Lundi à vendredi"
     *   - [1..6,0]    → "Tous les jours"
     *   - [0,6]       → "Samedi et dimanche"
     *   - autre       → liste explicite "Lundi, mardi, jeudi"
     *
     * @param list<int> $daysOpen
     */
    private static function formatDaysLabel(array $daysOpen): string
    {
        $names = [
            0 => 'dimanche',
            1 => 'lundi',
            2 => 'mardi',
            3 => 'mercredi',
            4 => 'jeudi',
            5 => 'vendredi',
            6 => 'samedi',
        ];

        $set = array_flip($daysOpen);

        if (count($daysOpen) === 7) {
            return 'Tous les jours';
        }
        if ([1, 2, 3, 4, 5] === $daysOpen) {
            return 'Lundi à vendredi';
        }
        if ([0, 6] === $daysOpen) {
            return 'Samedi et dimanche';
        }
        if (count($daysOpen) === 6 && !isset($set[0])) {
            return 'Du lundi au samedi';
        }
        if (count($daysOpen) === 6 && !isset($set[6])) {
            return 'Du dimanche au vendredi';
        }

        $labels = array_map(fn(int $d) => $names[$d] ?? '?', $daysOpen);
        return ucfirst(implode(', ', $labels));
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }
}
