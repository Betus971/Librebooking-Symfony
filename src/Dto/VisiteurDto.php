<?php
namespace App\Dto;

class VisiteurDto
{
public string $nom;
public string $prenom;
public \DateTimeInterface $dateNaissance;

public function __construct(string $nom, string $prenom, \DateTimeInterface $dateNaissance)
{
$this->nom = $nom;
$this->prenom = $prenom;
$this->dateNaissance = $dateNaissance;
}

// Ajoutez des méthodes de validation si nécessaire
}