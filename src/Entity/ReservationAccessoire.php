<?php

namespace App\Entity;

use App\Repository\ReservationAccessoireRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Ligne « accessoire demandé » d'une réservation : relie une
 * {@see ReservationSeries} à un {@see Accessoire} avec la quantité demandée.
 *
 * C'est un ManyToMany « avec charge utile » (la quantité), donc modélisé par
 * une entité dédiée plutôt qu'une simple table de jonction — même logique que
 * le nombre de participants, mais par accessoire.
 */
#[ORM\Entity(repositoryClass: ReservationAccessoireRepository::class)]
#[ORM\Table(name: 'reservation_accessoire')]
#[ORM\UniqueConstraint(name: 'uniq_resa_accessoire', columns: ['series_id', 'accessoire_id'])]
class ReservationAccessoire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'reservationAccessoires')]
    #[ORM\JoinColumn(name: 'series_id', nullable: false, onDelete: 'CASCADE')]
    private ?ReservationSeries $series = null;

    #[ORM\ManyToOne(inversedBy: 'reservationAccessoires')]
    #[ORM\JoinColumn(name: 'accessoire_id', nullable: false, onDelete: 'CASCADE')]
    private ?Accessoire $accessoire = null;

    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    private int $quantiteDemandee = 1;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getAccessoire(): ?Accessoire
    {
        return $this->accessoire;
    }

    public function setAccessoire(?Accessoire $accessoire): static
    {
        $this->accessoire = $accessoire;

        return $this;
    }

    public function getQuantiteDemandee(): int
    {
        return $this->quantiteDemandee;
    }

    public function setQuantiteDemandee(int $quantiteDemandee): static
    {
        $this->quantiteDemandee = $quantiteDemandee;

        return $this;
    }
}
