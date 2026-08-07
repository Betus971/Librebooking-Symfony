<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\BlackoutInstanceRepository;

#[ORM\Entity(repositoryClass: BlackoutInstanceRepository::class)]
#[ORM\Table(name: 'blackout_instances')]
class BlackoutInstance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(name: 'start_date', type: 'datetime')]
    private \DateTimeInterface $startDate;

    #[ORM\Column(name: 'end_date', type: 'datetime')]
    private \DateTimeInterface $endDate;

    #[ORM\ManyToOne(targetEntity: BlackoutSeries::class, inversedBy: 'instances')]
    #[ORM\JoinColumn(name: 'blackout_series_id' , nullable: false, onDelete: 'CASCADE')]
    private ?BlackoutSeries $series = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStartDate(): \DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $d): self
    {
        $this->startDate = $d;
        return $this;
    }

    public function getEndDate(): \DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeInterface $d): self
    {
        $this->endDate = $d;
        return $this;
    }

    public function getSeries(): ?BlackoutSeries
    {
        return $this->series;
    }

    public function setSeries(?BlackoutSeries $s): self
    {
        $this->series = $s;
        return $this;
    }
}

