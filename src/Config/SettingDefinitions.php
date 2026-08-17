<?php

namespace App\Config;

/**
 * Registre des réglages de l'application — SOURCE DE VÉRITÉ (en code).
 *
 * Chaque entrée : cle => [section, label, type, default, (help?)]
 *   - type ∈ {string, text, bool, int}
 *   - default : valeur appliquée tant qu'aucune surcharge n'existe en base
 *
 * Ajouter un réglage = une ligne ici (aucune migration nécessaire). Il apparaît
 * alors dans l'écran /admin/configuration et devient lisible via `setting('…')`.
 */
final class SettingDefinitions
{
    public const SETTINGS = [
        // --- Général ---
        'general.app_title'    => ['general', "Titre de l'application", 'string', 'Librebooking'],
        'general.app_subtitle' => ['general', 'Sous-titre',            'string', 'Réservation de ressources'],
        'general.entity_label' => ['general', "Nom de l'organisation", 'string', 'Mon organisation'],
        'general.timezone'     => ['general', 'Fuseau horaire',        'string', 'Europe/Paris'],

        // --- Réservations ---
        'reservation.cancel_delay_hours' => ['reservation', "Délai minimum d'annulation (heures)", 'int', 24],

        // --- E-mails ---
        'mail.from_name'    => ['mail', "Nom de l'expéditeur",     'string', 'Librebooking'],
        'mail.from_address' => ['mail', "Adresse de l'expéditeur", 'string', 'no-reply@example.com'],

        // --- Modules ---
        'modules.presta_enabled' => ['modules', 'Activer le module Prestations', 'bool', true],
    ];

    /** Libellés lisibles des sections (pour l'écran admin). */
    public const SECTION_LABELS = [
        'general'     => 'Général',
        'reservation' => 'Réservations',
        'mail'        => 'E-mails',
        'modules'     => 'Modules',
    ];

    /**
     * Clés regroupées par section, pour construire le formulaire.
     *
     * @return array<string, string[]>
     */
    public static function bySection(): array
    {
        $out = [];
        foreach (self::SETTINGS as $cle => [$section]) {
            $out[$section][] = $cle;
        }
        return $out;
    }

    public static function sectionLabel(string $section): string
    {
        return self::SECTION_LABELS[$section] ?? ucfirst($section);
    }
}
