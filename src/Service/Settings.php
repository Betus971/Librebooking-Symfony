<?php

namespace App\Service;

use App\Config\SettingDefinitions;
use App\Entity\AppSetting;
use App\Repository\AppSettingRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Lecture/écriture des réglages de l'application.
 *
 * `get('cle')` renvoie la surcharge en base si elle existe, sinon la valeur par
 * défaut du registre {@see SettingDefinitions}. Les surcharges sont chargées une
 * seule fois par requête (cache mémoire), invalidé à chaque `set()`.
 */
final class Settings
{
    /** @var array<string,?string>|null surcharges (cle => valeur brute) */
    private ?array $overrides = null;

    public function __construct(
        private readonly AppSettingRepository $repo,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function get(string $key): mixed
    {
        $def = SettingDefinitions::SETTINGS[$key]
            ?? throw new \InvalidArgumentException("Réglage inconnu : $key");

        $raw = $this->overrides()[$key] ?? null;

        return $raw === null ? $def[3] /* default */ : $this->cast($raw, $def[2] /* type */);
    }

    public function set(string $key, mixed $value): void
    {
        if (!isset(SettingDefinitions::SETTINGS[$key])) {
            throw new \InvalidArgumentException("Réglage inconnu : $key");
        }

        $s = $this->repo->findOneBy(['cle' => $key]) ?? (new AppSetting())->setCle($key);
        $s->setValeur($this->serialize($value));
        $this->em->persist($s);
        $this->em->flush();

        $this->overrides = null; // invalide le cache mémoire
    }

    /** @return array<string,?string> */
    private function overrides(): array
    {
        if ($this->overrides === null) {
            $this->overrides = [];
            try {
                foreach ($this->repo->findAll() as $s) {
                    $this->overrides[$s->getCle()] = $s->getValeur();
                }
            } catch (\Throwable) {
                // Table absente (migration non jouée) ou base indisponible :
                // on reste sur les valeurs par défaut du registre plutôt que de casser toutes les pages.
            }
        }

        return $this->overrides;
    }

    private function cast(string $raw, string $type): mixed
    {
        return match ($type) {
            'bool' => $raw === '1' || $raw === 'true',
            'int'  => (int) $raw,
            default => $raw, // string | text
        };
    }

    private function serialize(mixed $v): string
    {
        return match (true) {
            is_bool($v) => $v ? '1' : '0',
            default     => (string) $v,
        };
    }
}
