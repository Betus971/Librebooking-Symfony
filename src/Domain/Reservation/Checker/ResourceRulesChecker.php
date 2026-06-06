<?php
namespace App\Domain\Reservation\Checker;

use App\Entity\Resource;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

final class ResourceRulesChecker
{
    /**
     * @return string[] Liste de messages bloquants (vide si OK)
     */
    public function check(Resource $resource, DateTimeInterface $start, DateTimeInterface $end, ?DateTimeInterface $now = null): array
    {
        $errors = [];

        // 0) Now (timezone cohérente avec le start)
        $now ??= new DateTimeImmutable('now', $start->getTimezone() ?: new DateTimeZone('UTC'));

        // 1) Ressource active ?
        if (method_exists($resource, 'isActive') && !$resource->isActive()) {
            $errors[] = "Cette ressource est inactive et ne peut pas être réservée.";
            return $errors;
        }

        // 2) Durée en minutes
        $minutes = (int) round(($end->getTimestamp() - $start->getTimestamp()) / 60);
        if ($minutes <= 0) {
            return ["La fin doit être postérieure au début."];
        }

        // 3) Min / Max (entier minutes, nullable) – messages lisibles
        if (method_exists($resource, 'getMinDuration')) {
            $min = $resource->getMinDuration();
            if (null !== $min && $minutes < $min) {
                $errors[] = "La durée est inférieure à la durée minimale autorisée (min. ".$this->humanDelay($min).").";
            }
        }
        if (method_exists($resource, 'getMaxDuration')) {
            $max = $resource->getMaxDuration();
            if (null !== $max && $minutes > $max) {
                $errors[] = "La durée dépasse la durée maximale autorisée (max. ".$this->humanDelay($max).").";
            }
        }

        // 4) Multi-jour
        $allowMultiday = method_exists($resource, 'isAllowMultiday')
            ? (bool) $resource->isAllowMultiday()
            : (method_exists($resource, 'getAllowMultiday') ? (bool) $resource->getAllowMultiday() : true);

        if (!$allowMultiday && $start->format('Y-m-d') !== $end->format('Y-m-d')) {
            $errors[] = "Les réservations ne peuvent pas s'étendre sur plusieurs jours pour cette ressource.";
        }

        // 5) Pas de créneau (minIncrement) : alignement + multiple
        if (method_exists($resource, 'getMinIncrement')) {
            $inc = (int) $resource->getMinIncrement();
            if ($inc > 0) {
                $startMinutesOfDay = ((int) $start->format('H')) * 60 + (int) $start->format('i');

                if ($startMinutesOfDay % $inc !== 0) {
                    $errors[] = "L’heure de début doit être alignée sur un pas de ".$this->humanDelay($inc)." (ex. tranches de ".$this->humanDelay($inc).").";
                }
                if ($minutes % $inc !== 0) {
                    $errors[] = "La durée doit respecter le pas de ".$this->humanDelay($inc)." (pas de créneau).";
                }
            }
        }

        // 6) Préavis de création (minNoticeTimeAdd) et anticipation max (maxNoticeTime)
        //    -> messages “clairs” au lieu de “1440 min”
        if (method_exists($resource, 'getMinNoticeTimeAdd')) {
            $preAdd = $resource->getMinNoticeTimeAdd();
            if (null !== $preAdd) {
                $limit = $now->modify("+{$preAdd} minutes");
                if ($limit > $start) {
                    $errors[] = "Les réservations doivent être créées au moins ".$this->humanDelay($preAdd)." avant l’heure de début.";
                }
            }
        }
        if (method_exists($resource, 'getMaxNoticeTime')) {
            $maxAhead = $resource->getMaxNoticeTime();
            if (null !== $maxAhead) {
                $tooEarlyAfter = $now->modify("+{$maxAhead} minutes");
                if ($start > $tooEarlyAfter) {
                    $errors[] = "On ne peut pas réserver plus de ".$this->humanDelay($maxAhead)." à l’avance.";
                }
            }
        }

        // (Optionnel) BufferTime & chevauchements :
        // => nécessite l’accès aux instances; à intégrer via un AvailabilityChecker dédié.

        return $errors;
    }

    /**
     * Transforme des minutes en libellé court lisible : 1 j, 2 h, 30 min, 1 h 30 min, etc.
     */
    private function humanDelay(int $minutes): string
    {
        $d = intdiv($minutes, 1440);
        $r = $minutes % 1440;
        $h = intdiv($r, 60);
        $m = $r % 60;

        $parts = [];
        if ($d > 0) { $parts[] = $d.' '.($d > 1 ? 'jours' : 'jour'); }
        if ($h > 0) { $parts[] = $h.' '.($h > 1 ? 'heures' : 'heure'); }
        if ($m > 0 || empty($parts)) { $parts[] = $m.' min'; }

        // préférer une forme compacte quand c’est “pile”
        if ($d > 0 && $h === 0 && $m === 0) return $d.' '.($d>1?'jours':'jour');
        if ($h > 0 && $m === 0 && $d === 0) return $h.' '.($h>1?'heures':'heure');

        return implode(' ', $parts);
    }
}
