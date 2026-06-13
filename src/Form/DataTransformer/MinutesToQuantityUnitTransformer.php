<?php

namespace App\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

final class MinutesToQuantityUnitTransformer implements DataTransformerInterface
{
    /** @var array<string, int> */
    private array $factors = [
        'd' => 1440,    // jours
        'h' => 60,      // heures
        'm' => 1,       // minutes
    ];

    /** @param array<string> $allowedUnits */
    public function __construct(
        private string $defaultUnit = 'm',
        private array $allowedUnits = ['d', 'h', 'm']
    ) {
        foreach ($this->allowedUnits as $u) {
            if (!isset($this->factors[$u])) {
                throw new \InvalidArgumentException(sprintf('Unité "%s" non supportée.', $u));
            }
        }
        if (!in_array($this->defaultUnit, $this->allowedUnits, true)) {
            $this->defaultUnit = $this->allowedUnits[0];
        }
    }

    /** @param mixed $minutes */
    public function transform($minutes): array
    {
        if ($minutes === null || $minutes === '') {
            return ['value' => null, 'unit' => $this->defaultUnit];
        }

        if (!is_numeric($minutes)) {
            throw new TransformationFailedException('La valeur de minutes doit être numérique.');
        }

        $minutes = (int) $minutes;

        // Trie les unités autorisées par ordre décroissant de facteur
        $sortedUnits = $this->allowedUnits;
        usort($sortedUnits, fn($a, $b) => $this->factors[$b] <=> $this->factors[$a]);

        foreach ($sortedUnits as $u) {
            if ($minutes % $this->factors[$u] === 0) {
                return ['value' => (int) ($minutes / $this->factors[$u]), 'unit' => $u];
            }
        }

        // Cas par défaut (ex: 90min → 90m)
        return ['value' => $minutes, 'unit' => $this->defaultUnit];
    }

    /** @param array{value: ?int, unit: string}|null $data */
    public function reverseTransform($data): ?int
    {
        if ($data === null) {
            return null;
        }

        if (!is_array($data) || !array_key_exists('value', $data) || !array_key_exists('unit', $data)) {
            throw new TransformationFailedException('Format invalide pour la durée.');
        }

        $value = $data['value'];
        $unit = $data['unit'];

        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            throw new TransformationFailedException('La valeur doit être un nombre.');
        }

        if (!in_array($unit, $this->allowedUnits, true)) {
            throw new TransformationFailedException('Unité non autorisée.');
        }

        $value = (int) $value;
        if ($value < 0) {
            throw new TransformationFailedException('La valeur doit être positive ou nulle.');
        }

        return $value * $this->factors[$unit];
    }

    // Méthode utilitaire pour formater l'affichage (ex: "2h 30min")
    public function format(?int $minutes): string
    {
        if ($minutes === null) {
            return '';
        }

        $d = (int)($minutes / 1440);
        $h = (int)(($minutes % 1440) / 60);
        $m = $minutes % 60;

        $parts = [];
        if ($d > 0) $parts[] = "$d j";
        if ($h > 0) $parts[] = "$h h";
        if ($m > 0 || $minutes === 0) $parts[] = "$m min";

        return implode(' ', $parts);
    }
}
