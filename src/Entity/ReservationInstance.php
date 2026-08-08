<?php
namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
    #[ORM\Entity]
    #[ORM\Table(name: 'reservation_instances')]
    #[ORM\Index(name: 'idx_start_date', columns: ['start_date'])]
    #[ORM\Index(name: 'idx_end_date', columns: ['end_date'])]
    #[ORM\Index(name: 'idx_reference_number', columns: ['reference_number'])]
class ReservationInstance
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'instances')]
    #[ORM\JoinColumn(name: 'series_id', nullable: false, onDelete: 'CASCADE')]
    private ReservationSeries $series;

    #[ORM\Column(name: 'start_date', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $startDate;

    #[ORM\Column(name: 'end_date', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $endDate;

    #[ORM\Column(name: 'reference_number', length: 50 /* , unique: true en PROD */)]
    private string $referenceNumber;// TODO(prod): passer en unique + migration DB (index unique)

    #[ORM\Column(name: 'checkin_date', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $checkinDate = null;

    #[ORM\Column(name: 'checkout_date', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $checkoutDate = null;

    #[ORM\Column(name: 'previous_end_date', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $previousEndDate = null;

    /** Date d'envoi du rappel e-mail (null = pas encore envoyé). Utilisé par SendRemindersCommand. */
    #[ORM\Column(name: 'reminder_sent_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $reminderSentAt = null;

    #[ORM\OneToMany(mappedBy: 'reservationInstance', targetEntity: ReservationUser::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $reservationUsers;

    public function __construct()
    {
        $this->reservationUsers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $start): self
    {
        $this->startDate = $start;
        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeInterface $end): self
    {
        $this->endDate = $end;
        return $this;
    }

    public function getReferenceNumber(): ?string
    {
        return $this->referenceNumber;
    }

    public function setReferenceNumber(string $referenceNumber): static
    {
        $this->referenceNumber = $referenceNumber;

        return $this;
    }


    public function getCheckinDate(): ?\DateTimeInterface
    {
        return $this->checkinDate;
    }

    public function setCheckinDate(?\DateTimeInterface $d): self
    {
        $this->checkinDate = $d;
        return $this;
    }

    public function getCheckoutDate(): ?\DateTimeInterface
    {
        return $this->checkoutDate;
    }

    public function setCheckoutDate(?\DateTimeInterface $d): self
    {
        $this->checkoutDate = $d;
        return $this;
    }

    public function getPreviousEndDate(): ?\DateTimeInterface
    {
        return $this->previousEndDate;
    }

    public function setPreviousEndDate(?\DateTimeInterface $d): self
    {
        $this->previousEndDate = $d;
        return $this;
    }

    public function getReminderSentAt(): ?\DateTimeImmutable
    {
        return $this->reminderSentAt;
    }

    public function setReminderSentAt(?\DateTimeImmutable $reminderSentAt): self
    {
        $this->reminderSentAt = $reminderSentAt;
        return $this;
    }

    public function getSeries(): ?ReservationSeries
    {
        return $this->series;
    }

    public function setSeries(?ReservationSeries $series): static
    {
        $this->series = $series;

        return $this;
    }

    /**
     * @return Collection<int, ReservationUser>
     */
    public function getReservationUsers(): Collection
    {
        return $this->reservationUsers;
    }

    public function addReservationUser(ReservationUser $reservationUser): static
    {
        if (!$this->reservationUsers->contains($reservationUser)) {
            $this->reservationUsers->add($reservationUser);
            $reservationUser->setReservationInstance($this);
        }

        return $this;
    }

    public function removeReservationUser(ReservationUser $reservationUser): static
    {
        if ($this->reservationUsers->removeElement($reservationUser)) {
            // set the owning side to null (unless already changed)
            if ($reservationUser->getReservationInstance() === $this) {
                $reservationUser->setReservationInstance(null);
            }
        }

        return $this;
    }
}
