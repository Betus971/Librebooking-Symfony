<?php

namespace App\Presta\Service;

use App\Presta\Entity\Service;
use App\Presta\Repository\PlageHoraireRepository;
use App\Presta\Repository\PrestaAbsenceRepository;
use App\Presta\Repository\SessionRepository;

/**
 * Génère les créneaux disponibles d'un service individuel.
 *
 * Conçu pour éviter le N+1 : sur une plage de dates (semaine, mois), on charge
 * les plages horaires, les sessions bloquantes et les absences du prestataire
 * en TROIS requêtes au total (et non trois par jour), puis on calcule les
 * créneaux en mémoire jour par jour.
 *
 * Le résultat est strictement identique à l'ancien calcul inline du
 * contrôleur (mêmes règles de chevauchement, créneaux passés exclus).
 */
final class CreneauGenerator
{
    public function __construct(
        private readonly PlageHoraireRepository $plageRepository,
        private readonly SessionRepository $sessionRepository,
        private readonly PrestaAbsenceRepository $absenceRepository,
    ) {
    }

    /**
     * Créneaux disponibles pour une seule date.
     *
     * @return list<array{start: \DateTimeInterface, end: \DateTimeInterface, startStr: string}>
     */
    public function generateForDate(Service $service, \DateTimeInterface $date): array
    {
        $key = $date->format('Y-m-d');

        return $this->generateForRange($service, $date, $date)[$key] ?? [];
    }

    /**
     * Créneaux disponibles pour chaque jour d'une plage [from, to] (inclus).
     * Charge plages/sessions/absences UNE seule fois pour toute la plage.
     *
     * @return array<string, list<array{start: \DateTimeInterface, end: \DateTimeInterface, startStr: string}>>
     *         Indexé par date au format Y-m-d.
     */
    public function generateForRange(Service $service, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $prestataire = $service->getPrestataire();
        $duree = $service->getDureeMinutes();

        // Fuseau EXPLICITE Europe/Paris : les créneaux sont des heures « mur »
        // (ex. 14:00 à Paris). On compare avec MAINTENANT dans le même fuseau,
        // sinon le fuseau par défaut du serveur (souvent UTC) décale le filtre
        // « pas de créneau passé » d'une ou deux heures.
        $tz = new \DateTimeZone('Europe/Paris');

        $rangeStart = (new \DateTime($from->format('Y-m-d'), $tz))->setTime(0, 0, 0);
        $rangeEnd = (new \DateTime($to->format('Y-m-d'), $tz))->setTime(23, 59, 59);
        $now = new \DateTime('now', $tz);

        // Fenêtre glissante : aucun créneau au-delà de l'horizon du prestataire
        // (aujourd'hui + N jours). Comme « aujourd'hui » avance, la fenêtre glisse
        // toute seule chaque jour.
        $maxBooking = $prestataire->getMaxBookingDate();

        // ── 3 requêtes pour toute la plage ──────────────────────────────
        // Plages horaires indexées par jour de semaine (1=lundi … 7=dimanche).
        $plagesByJour = [];
        foreach ($this->plageRepository->findByPrestataire($prestataire) as $plage) {
            $plagesByJour[$plage->getJourSemaine()][] = $plage;
        }

        $sessions = $this->sessionRepository->findBlockingForPrestataireBetween($prestataire, $rangeStart, $rangeEnd);
        $absences = $this->absenceRepository->findForPrestataireBetween($prestataire, $rangeStart, $rangeEnd);

        // ── Calcul jour par jour, en mémoire ────────────────────────────
        $result = [];
        $cursor = (new \DateTime($from->format('Y-m-d'), $tz))->setTime(0, 0, 0);
        $last = (new \DateTime($to->format('Y-m-d'), $tz))->setTime(0, 0, 0);

        while ($cursor <= $last) {
            $dayKey = $cursor->format('Y-m-d');
            $jourSemaine = (int) $cursor->format('N');
            $result[$dayKey] = $this->buildCreneauxForDay(
                $cursor,
                $duree,
                $plagesByJour[$jourSemaine] ?? [],
                $sessions,
                $absences,
                $now,
                $maxBooking,
            );
            $cursor->modify('+1 day');
        }

        return $result;
    }

    /**
     * @param iterable<\App\Presta\Entity\PlageHoraire>   $plagesDuJour
     * @param iterable<\App\Presta\Entity\Session>        $sessions
     * @param iterable<\App\Presta\Entity\PrestaAbsence>  $absences
     *
     * @return list<array{start: \DateTimeInterface, end: \DateTimeInterface, startStr: string}>
     */
    private function buildCreneauxForDay(
        \DateTimeInterface $day,
        int $duree,
        iterable $plagesDuJour,
        iterable $sessions,
        iterable $absences,
        \DateTimeInterface $now,
        \DateTimeInterface $maxBooking,
    ): array {
        $creneaux = [];

        foreach ($plagesDuJour as $plage) {
            // Période de validité optionnelle : on ignore la plage hors de sa
            // fenêtre de dates (si elle en a une).
            if (!$plage->isActiveOn($day)) {
                continue;
            }

            $currentStart = (clone $day);
            $currentStart->setTime((int) $plage->getHeureDebut()->format('H'), (int) $plage->getHeureDebut()->format('i'));

            $endPlage = (clone $day);
            $endPlage->setTime((int) $plage->getHeureFin()->format('H'), (int) $plage->getHeureFin()->format('i'));

            while ($currentStart < $endPlage) {
                $currentEnd = (clone $currentStart)->modify('+' . $duree . ' minutes');

                if ($currentEnd > $endPlage) {
                    break;
                }

                // Ni dans le passé, ni au-delà de l'horizon de réservation.
                if ($currentStart > $now
                    && $currentStart <= $maxBooking
                    && !$this->overlaps($currentStart, $currentEnd, $sessions, $absences)) {
                    $creneaux[] = [
                        'start' => clone $currentStart,
                        'end' => clone $currentEnd,
                        'startStr' => $currentStart->format('Y-m-d H:i'),
                    ];
                }

                $currentStart->modify('+' . $duree . ' minutes');
            }
        }

        return $creneaux;
    }

    /**
     * @param iterable<\App\Presta\Entity\Session>       $sessions
     * @param iterable<\App\Presta\Entity\PrestaAbsence> $absences
     */
    private function overlaps(
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        iterable $sessions,
        iterable $absences,
    ): bool {
        // Chevauchement si (Debut1 < Fin2) ET (Debut2 < Fin1).
        foreach ($sessions as $s) {
            if ($start < $s->getDateFin() && $s->getDateDebut() < $end) {
                return true;
            }
        }
        foreach ($absences as $a) {
            if ($start < $a->getDateFin() && $a->getDateDebut() < $end) {
                return true;
            }
        }

        return false;
    }

    /**
     * Recherche la prochaine date ayant au moins un créneau disponible,
     * en cherchant jusqu'à l'horizon de réservation du prestataire.
     */
    public function findNextAvailableDate(Service $service, \DateTimeInterface $from): ?\DateTimeInterface
    {
        $maxBooking = clone $service->getPrestataire()->getMaxBookingDate();
        $maxBooking->setTime(23, 59, 59);
        $cursor = (clone $from)->modify('+1 day')->setTime(0, 0, 0);

        if ($cursor > $maxBooking) {
            return null;
        }

        $currentStart = clone $cursor;
        
        while ($currentStart <= $maxBooking) {
            $currentEnd = (clone $currentStart)->modify('+6 days')->setTime(23, 59, 59);
            if ($currentEnd > $maxBooking) {
                $currentEnd = clone $maxBooking;
            }

            $range = $this->generateForRange($service, $currentStart, $currentEnd);
            
            foreach ($range as $dateKey => $creneaux) {
                if (count($creneaux) > 0) {
                    return new \DateTime($dateKey);
                }
            }
            
            $currentStart->modify('+7 days');
        }

        return null;
    }
}
