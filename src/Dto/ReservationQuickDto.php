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

    /** Nombre de participants (exigé selon la ressource). */
    public ?int $nombreParticipants = null;

    /**
     * Accessoires demandés : map accessoireId => quantité demandée.
     * Alimenté depuis la section « Accessoires » du formulaire de réservation.
     * La validation du stock (quantité <= disponible) se fait dans
     * ReservationManager, qui a accès au catalogue des accessoires.
     *
     * @var array<int,int>
     */
    public array $accessoires = [];

    /**
     * Règle métier : le nombre de participants est OBLIGATOIRE si la ressource
     * l'exige (amphis/salles), et ne peut pas dépasser sa capacité.
     */
    #[Assert\Callback]
    public function validateParticipants(ExecutionContextInterface $context): void
    {
        if ($this->resource === null) {
            return;
        }

        $max = $this->resource->getMaxParticipants();

        if ($this->resource->isRequiresParticipants()
            && ($this->nombreParticipants === null || $this->nombreParticipants < 1)) {
            $context->buildViolation('Le nombre de participants est obligatoire pour cette ressource.')
                ->atPath('nombreParticipants')
                ->addViolation();
            return;
        }

        if ($this->nombreParticipants !== null && $max !== null && $this->nombreParticipants > $max) {
            $context->buildViolation(sprintf(
                'Cette ressource a une capacité de %d participant%s maximum.',
                $max, $max > 1 ? 's' : ''
            ))
                ->atPath('nombreParticipants')
                ->addViolation();
        }
    }
}
