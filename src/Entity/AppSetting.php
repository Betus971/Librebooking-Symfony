<?php

namespace App\Entity;

use App\Repository\AppSettingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Surcharge d'un réglage de l'application (stockée en base).
 *
 * Seules les valeurs MODIFIÉES par l'admin sont stockées ici ; la liste des
 * réglages disponibles, leurs libellés, types et valeurs par défaut vivent dans
 * {@see \App\Config\SettingDefinitions}. Le service {@see \App\Service\Settings}
 * fusionne les deux (surcharge en base sinon valeur par défaut du code).
 */
#[ORM\Entity(repositoryClass: AppSettingRepository::class)]
#[ORM\Table(name: 'app_setting')]
#[ORM\UniqueConstraint(name: 'uniq_app_setting_cle', columns: ['cle'])]
class AppSetting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Clé du réglage, ex. "general.app_title" (doit exister dans SettingDefinitions). */
    #[ORM\Column(length: 100, unique: true)]
    private string $cle;

    /** Valeur brute (sérialisée en texte ; castée à la lecture selon le type). */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $valeur = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCle(): string
    {
        return $this->cle;
    }

    public function setCle(string $cle): self
    {
        $this->cle = $cle;
        return $this;
    }

    public function getValeur(): ?string
    {
        return $this->valeur;
    }

    public function setValeur(?string $valeur): self
    {
        $this->valeur = $valeur;
        return $this;
    }
}
