<?php
namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
#[ORM\Entity]
#[ORM\Table(name: 'reservation_resources')]
#[ORM\UniqueConstraint(name: 'uniq_series_resource', columns: ['series_id','resource_id'])]
class ReservationResource
{
    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'reservationResources')]
    #[ORM\JoinColumn(name: 'series_id', nullable: false, onDelete: 'CASCADE')]
    private ReservationSeries $series;

    #[ORM\Id]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'resource_id', nullable: false, onDelete: 'CASCADE')]
    private Resource $resource;

    #[ORM\Column(name: 'resource_level_id', type: 'smallint', options: ['unsigned' => true])]
    private int $resourceLevelId = 1;

    public function __toString(): string
    {
        return isset($this->resource) ? (string) $this->resource : '?';
    }

    public function getResourceLevelId(): ?int
    {
        return $this->resourceLevelId;
    }

    public function setResourceLevelId(int $resourceLevelId): static
    {
        $this->resourceLevelId = $resourceLevelId;

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

    public function getResource(): ?Resource
    {
        return $this->resource;
    }

    public function setResource(?Resource $resource): static
    {
        $this->resource = $resource;

        return $this;
    }
}
