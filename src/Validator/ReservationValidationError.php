<?php

namespace App\Validator;

/**
 * Résultat d'une validation métier en échec : le champ de formulaire à mettre
 * en avant ('startError' | 'endError' | 'formError') et le message à afficher.
 */
final class ReservationValidationError
{
    public function __construct(
        public readonly string $field,
        public readonly string $message,
        /** Code machine optionnel (ex. 'unavailable') pour adapter l'UI. */
        public readonly ?string $code = null,
    ) {
    }
}
