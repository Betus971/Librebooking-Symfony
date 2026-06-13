<?php
namespace App\Dto;

use App\Entity\Resource;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class ReservationQuickDto
{
    #[Assert\NotBlank] public string $title = '';
    #[Assert\NotNull]  public ?\App\Entity\Resource $resource = null;

//    #[Assert\NotNull(message: 'La date/heure de début est obligatoire.')]
//    public ?\DateTimeInterface $start = null;
//
//    #[Assert\NotNull]
//    #[Assert\GreaterThan(propertyPath: 'start', message: 'La fin doit être après le début.')]
//    public ?\DateTimeInterface $end = null;

    public ?\DateTimeInterface $start = null;
    public ?\DateTimeInterface $end = null;
    public ?string $description = null;
}
