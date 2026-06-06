<?php
namespace App\Dto;

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class VisiteurSearchDto
{
    #[Assert\Type('string')]
    public ?string $nom = null;

    #[Assert\Type('string')]
    public ?string $prenom = null;

    #[Assert\Type(\DateTimeInterface::class)]
    public ?\DateTimeInterface $datearriveeMin = null;

    #[Assert\Type(\DateTimeInterface::class)]
    public ?\DateTimeInterface $datearriveeMax = null;

    // Méthodes pour définir les dates sous forme de chaînes de caractères
    public function setDateArriveeMin(?\DateTimeInterface $date): void
    {
        $this->datearriveeMin = $date;
    }

    public function setDateArriveeMax(?\DateTimeInterface $date): void
    {
        $this->datearriveeMax = $date;
    }
}