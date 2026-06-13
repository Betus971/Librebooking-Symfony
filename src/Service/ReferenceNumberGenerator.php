<?php
namespace App\Service;

use Random\RandomException;

final class ReferenceNumberGenerator
{
    public function generate(\DateTimeInterface $when = new \DateTimeImmutable()): string
    {
        // Ex: RES-20251013-AB12CD (date + 6 chars hex)
        try {
            $rand = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        } catch (RandomException $e) {
            // random_bytes() indisponible (très rare) : fallback non-cryptographique
            $rand = strtoupper(substr(md5(uniqid((string) mt_rand(), true)), 0, 6));
        }

        return 'RES-' . $when->format('Ymd') . '-' . $rand;
    }
}
