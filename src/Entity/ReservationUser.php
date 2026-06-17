<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'reservation_users')]
class ReservationUser
{
    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'reservationUsers')]
    #[ORM\JoinColumn(name: 'reservation_instance_id', nullable: false, onDelete: 'CASCADE')]
    private ReservationInstance $reservationInstance;

    #[ORM\Id]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(name: 'reservation_user_level', type: 'smallint', options: ['unsigned' => true])]
    private int $level = 0;

    public function getLevel(): ?int
    {
        return $this->level;
    }

    public function setLevel(int $level): static
    {
        $this->level = $level;

        return $this;
    }

    public function getReservationInstance(): ?ReservationInstance
    {
        return $this->reservationInstance;
    }

    public function setReservationInstance(?ReservationInstance $reservationInstance): static
    {
        $this->reservationInstance = $reservationInstance;

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
}
