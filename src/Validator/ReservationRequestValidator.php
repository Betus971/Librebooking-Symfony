<?php

namespace App\Validator;

use App\Entity\Resource;
use App\Repository\BlackoutInstanceRepository;
use App\Service\AvailabilityChecker;

/**
 * Centralise les règles métier d'une demande de réservation simple, dans le
 * MÊME ORDRE que l'ancienne logique du contrôleur.
 *
 * Retourne la première erreur rencontrée (ou null si tout est valide), sans
 * connaître HTTP : c'est au contrôleur de transformer l'erreur en réponse 422.
 */
final class ReservationRequestValidator
{
    public function __construct(
        private readonly BlackoutInstanceRepository $blackoutRepo,
        private readonly AvailabilityChecker $availability,
    ) {
    }

    public function validate(Resource $resource, ?\DateTimeInterface $start, ?\DateTimeInterface $end): ?ReservationValidationError
    {
        // 1) Pas de réservation dans le passé.
        if ($start && $start < new \DateTime('today')) {
            return new ReservationValidationError(
                'startError',
                'Impossible de réserver une date passée. Veuillez choisir une date à partir d\'aujourd\'hui.'
            );
        }

        // 2) La fin doit être après le début.
        if ($start && $end && $end <= $start) {
            return new ReservationValidationError(
                'endError',
                'L\'heure de fin doit être postérieure à l\'heure de début.'
            );
        }

        // 3) Fermeture (blackout).
        if ($this->blackoutRepo->hasBlackout($resource, $start, $end)) {
            return new ReservationValidationError(
                'formError',
                'Impossible de réserver : la ressource est fermée (maintenance, travaux…) sur ce créneau.'
            );
        }

        // 4) Plage de validité du planning.
        $schedule = $resource->getSchedule();
        if ($schedule) {
            $scheduleStart = $schedule->getStartDate();
            $scheduleEnd   = $schedule->getEndDate();

            if ($scheduleStart && $start < \DateTime::createFromImmutable($scheduleStart)) {
                return new ReservationValidationError(
                    'startError',
                    sprintf(
                        'Les réservations ne sont pas encore ouvertes. Date d\'ouverture : %s.',
                        $scheduleStart->format('d/m/Y')
                    )
                );
            }
            if ($scheduleEnd && $end > \DateTime::createFromImmutable($scheduleEnd)->modify('+1 day')) {
                return new ReservationValidationError(
                    'endError',
                    sprintf(
                        'Les réservations sont fermées après le %s.',
                        $scheduleEnd->format('d/m/Y')
                    )
                );
            }
        }

        // 5) Pré-check de disponibilité (hors verrou).
        if (!$this->availability->isFree($resource, $start, $end)) {
            return new ReservationValidationError(
                'formError',
                'Ce créneau n\'est pas disponible : la ressource est déjà réservée ou hors des horaires d\'ouverture.'
            );
        }

        return null;
    }
}
