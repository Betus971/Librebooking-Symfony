<?php


namespace App\Util;

final class WeekHelper
{
    public function fromIsoWeek(string $iso): IsoWeek
    {
        // attend "YYYY-Www" (ex: 2025-W42)
        if (!preg_match('/^(\d{4})-W(\d{2})$/', $iso, $m)) {
            $now = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'));
            return $this->fromDate($now);
        }
        return new IsoWeek((int)$m[1], (int)$m[2]);
    }

    public function fromDate(\DateTimeInterface $dt): IsoWeek
    {
        return new IsoWeek((int)$dt->format('o'), (int)$dt->format('W'));
    }

    public function isoWeekString(\DateTimeInterface $dt): string
    {
        return $this->fromDate($dt)->toString();
    }
}

final class IsoWeek
{
    public function __construct(public int $year, public int $week) {}

    public function toString(): string
    {
        return sprintf('%04d-W%02d', $this->year, $this->week);
    }

    public function startOfWeek(string $tz = 'Europe/Paris'): \DateTimeImmutable
    {
        $z = new \DateTimeZone($tz);
        $d = (new \DateTimeImmutable('now', $z))
            ->setISODate($this->year, $this->week, 1) // Lundi
            ->setTime(0, 0, 0);
        return $d;
    }

    public function endOfWeek(string $tz = 'Europe/Paris'): \DateTimeImmutable
    {
        return $this->startOfWeek($tz)->modify('+7 days'); // exclusif
    }

    public function next(): self
    {
        $startNext = $this->startOfWeek()->modify('+7 days');
        return new self((int)$startNext->format('o'), (int)$startNext->format('W'));
    }

    public function prev(): self
    {
        $startPrev = $this->startOfWeek()->modify('-7 days');
        return new self((int)$startPrev->format('o'), (int)$startPrev->format('W'));
    }
}
