<?php

namespace App\Service;

use App\Entity\Visitor;
use App\Presta\Entity\Session as PrestaSession;

/**
 * Génération de flux iCalendar (RFC 5545) — extrait de CalendarController (RF-12).
 *
 * La logique (formatage des dates, échappement, assemblage VCALENDAR/VEVENT)
 * est reprise telle quelle pour préserver strictement la sortie du flux ICS,
 * y compris le correctif d'échappement anti-injection CRLF (P1.7).
 */
final class IcsGeneratorService
{
    /**
     * Construit le flux ICS complet pour une liste de visiteurs.
     *
     * @param iterable<Visitor> $visitors
     */
    public function generateForVisitors(iterable $visitors): string
    {
        $ics  = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
     * Construit le flux ICS complet pour une liste de réservations.
     *
     * @param iterable<\App\Entity\ReservationInstance> $reservations
     */
    public function generateForReservations(iterable $reservations): string
    {
        $ics  = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//MonApp//GestionRessources//FR\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";
        $ics .= "METHOD:PUBLISH\r\n";

        foreach ($reservations as $resa) {
            $series = method_exists($resa, 'getSeries') ? $resa->getSeries() : null;
            if (!$series) {
                continue;
            }

            $title = $series->getTitle() ?: 'Réservation';

            // Demandeur (→ ORGANIZER + 1re ligne de description).
            $owner      = method_exists($series, 'getOwner') ? $series->getOwner() : null;
            $ownerName  = $owner ? trim(($owner->getFname() ?? '') . ' ' . ($owner->getLname() ?? '')) : null;
            $ownerEmail = $owner ? $owner->getEmail() : null;

            // Ressource(s) → LOCATION (champ dédié, mis en avant par les agendas).
            $locations = [];
            if (method_exists($series, 'getReservationResources')) {
                foreach ($series->getReservationResources() as $rr) {
                    if ($rr->getResource()) {
                        $locations[] = $rr->getResource()->getName();
                    }
                }
            }
            $location = implode(', ', $locations);

            // Description enrichie (ce que réclamait le service gestionnaire).
            $lines = [];
            if ($ownerName)        { $lines[] = 'Réservé par : ' . $ownerName; }
            if ($location !== '')  { $lines[] = 'Ressource(s) : ' . $location; }
            if (method_exists($series, 'getType') && $series->getType()) {
                $lines[] = 'Type : ' . $series->getType()->getLabel();
            }
            if (method_exists($series, 'getNombreParticipants') && $series->getNombreParticipants()) {
                $lines[] = 'Participants : ' . $series->getNombreParticipants();
            }
            if (method_exists($series, 'getReservationAccessoires') && count($series->getReservationAccessoires()) > 0) {
                $acc = [];
                foreach ($series->getReservationAccessoires() as $ra) {
                    $acc[] = $ra->getAccessoire()->getNom() . ' (x' . $ra->getQuantiteDemandee() . ')';
                }
                $lines[] = 'Accessoires : ' . implode(', ', $acc);
            }
            if (method_exists($resa, 'getReferenceNumber') && $resa->getReferenceNumber()) {
                $lines[] = 'Référence : ' . $resa->getReferenceNumber();
            }
            if ($series->getDescription()) {
                $lines[] = "\n" . $series->getDescription();
            }
            $description = $lines ? implode("\n", $lines) : 'Réservation';

            // STATUS iCalendar d'après le statut métier (1 En attente, 2 Confirmée,
            // 3 Refusée, 4 Annulée) — CONFIRMED par défaut.
            $statusIcs = 'CONFIRMED';
            if (method_exists($series, 'getStatus') && $series->getStatus()) {
                $sid = $series->getStatus()->getId();
                if (1 === $sid) {
                    $statusIcs = 'TENTATIVE';
                } elseif (\in_array($sid, [3, 4], true)) {
                    $statusIcs = 'CANCELLED';
                }
            }

            $ics .= $this->createEvent(
                'resa-' . $resa->getId(),
                $resa->getStartDate(),
                $resa->getEndDate(),
                $title,
                $description,
                $location,
                $ownerName,
                $ownerEmail,
                $statusIcs,
                true // heures en UTC (fuseau non ambigu pour tous les agendas)
            );
        }

        $ics .= "END:VCALENDAR";

        return $ics;
    }

    /**
     * Construit le flux ICS d'un rendez-vous « prestation » (module Presta),
     * pour l'ajout en un clic à l'agenda du client (façon Doctolib).
     */
    public function generateForPrestaSession(PrestaSession $session): string
    {
        $ics  = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//MonApp//GestionRessources//FR\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";
        $ics .= "METHOD:PUBLISH\r\n";

        $service = $session->getService();
        $presta  = $session->getPrestataire();
        $prestaName = $presta ? trim(($presta->getPrenom() ?? '') . ' ' . ($presta->getNom() ?? '')) : null;

        $summary = $service ? $service->getLibelle() : 'Rendez-vous';
        if ($prestaName) {
            $summary .= ' — ' . $prestaName;
        }

        $lines = [];
        if ($prestaName) { $lines[] = 'Prestataire : ' . $prestaName; }
        if ($service) {
            $lines[] = 'Prestation : ' . $service->getLibelle();
            $lines[] = 'Durée : ' . $service->getDureeMinutes() . ' min';
            if ($service->getDescription()) {
                $lines[] = "\n" . $service->getDescription();
            }
        }
        $description = $lines ? implode("\n", $lines) : 'Rendez-vous';

        $organizerEmail = ($presta && $presta->getUser()) ? $presta->getUser()->getEmail() : null;

        if ($session->getDateDebut() && $session->getDateFin()) {
            $ics .= $this->createEvent(
                'presta-' . $session->getId(),
                $session->getDateDebut(),
                $session->getDateFin(),
                $summary,
                $description,
                $prestaName ?? '',
                $prestaName,
                $organizerEmail,
                'CONFIRMED',
                true
            );
        }

        $ics .= "END:VCALENDAR";

        return $ics;
    }

    /**
     * Construit le flux ICS de l'agenda complet d'un prestataire.
     *
     * @param iterable<PrestaSession> $sessions
     */
    public function generateForPrestaAgenda(iterable $sessions): string
    {
        $ics  = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//MonApp//GestionRessources//FR\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";
        $ics .= "METHOD:PUBLISH\r\n";

        foreach ($sessions as $session) {
            $service = $session->getService();
            $presta  = $session->getPrestataire();
            $prestaName = $presta ? trim(($presta->getPrenom() ?? '') . ' ' . ($presta->getNom() ?? '')) : null;

            $summary = $service ? $service->getLibelle() : 'Rendez-vous';
            
            // Pour le prestataire, on affiche le nom du client dans le titre du RDV individuel
            if ($service && $service->getType() === 'INDIVIDUEL') {
                $firstInsc = $session->getInscriptions()->first();
                $clientLabel = $firstInsc 
                    ? trim(($firstInsc->getClient()->getFname() ?? '') . ' ' . ($firstInsc->getClient()->getLname() ?? '')) ?: $firstInsc->getClient()->getEmail()
                    : ($session->getClientNom() ?: 'Rendez-vous');
                $summary = $clientLabel . ' · ' . $summary;
            } elseif ($service && $service->getType() === 'GROUPE') {
                $summary = $summary . ' (' . $session->getNbInscrits() . '/' . $service->getCapaciteMax() . ')';
            }

            $lines = [];
            if ($service) {
                if ($service->getDescription()) {
                    $lines[] = $service->getDescription();
                }
            }
            if ($session->getNote()) {
                $lines[] = "\nNote : " . $session->getNote();
            }
            
            $description = $lines ? implode("\n", $lines) : 'Rendez-vous';

            $organizerEmail = ($presta && $presta->getUser()) ? $presta->getUser()->getEmail() : null;

            if ($session->getDateDebut() && $session->getDateFin()) {
                $ics .= $this->createEvent(
                    'presta-' . $session->getId(),
                    $session->getDateDebut(),
                    $session->getDateFin(),
                    $summary,
                    $description,
                    '',
                    $prestaName,
                    $organizerEmail,
                    'CONFIRMED',
                    true
                );
            }
        }

        $ics .= "END:VCALENDAR";

        return $ics;
    }

    /**
     * Génère un bloc VEVENT proprement formaté.
     *
     * @param bool $utc true → DTSTART/DTEND convertis en UTC (suffixe Z, non
     *                  ambigu) ; false → heure locale « flottante » (comportement
     *                  historique conservé pour les flux visiteurs).
     */
    private function createEvent(
        string $uid,
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        string $summary,
        string $description = '',
        string $location = '',
        ?string $organizerName = null,
        ?string $organizerEmail = null,
        string $status = 'CONFIRMED',
        bool $utc = false
    ): string {
        if ($utc) {
            $s = \DateTimeImmutable::createFromInterface($start)->setTimezone(new \DateTimeZone('UTC'));
            $e = \DateTimeImmutable::createFromInterface($end)->setTimezone(new \DateTimeZone('UTC'));
            $dtStart = $s->format('Ymd\THis\Z');
            $dtEnd   = $e->format('Ymd\THis\Z');
        } else {
            $dtStart = $start->format('Ymd\THis');
            $dtEnd   = $end->format('Ymd\THis');
        }
        $now = gmdate('Ymd\THis\Z'); // DTSTAMP toujours en UTC (standard).

        $summary     = $this->escape($summary);
        $description = $this->escape($description);

        $event  = "BEGIN:VEVENT\r\n";
        $event .= "UID:{$uid}@monapp.local\r\n";
        $event .= "DTSTAMP:{$now}\r\n";
        $event .= "DTSTART:{$dtStart}\r\n";
        $event .= "DTEND:{$dtEnd}\r\n";
        $event .= "SUMMARY:{$summary}\r\n";
        if ('' !== $location) {
            $event .= 'LOCATION:' . $this->escape($location) . "\r\n";
        }
        $event .= "DESCRIPTION:{$description}\r\n";
        if ($organizerEmail) {
            $cn = $organizerName ? ';CN=' . $this->escape($organizerName) : '';
            $event .= 'ORGANIZER' . $cn . ':mailto:' . $organizerEmail . "\r\n";
        }
        $event .= "STATUS:{$status}\r\n";
        $event .= "END:VEVENT\r\n";

        return $event;
    }

    /**
     * Échappement RFC 5545 robuste contre l'injection CRLF (P1.7).
     * L'ordre est important : backslash d'abord, puis fins de ligne, puis , et ;.
     */
    public function escape(string $string): string
    {
        return str_replace(
            ['\\',   "\r\n", "\r",  "\n",  ',',   ';'],
            ['\\\\', '\\n',  '\\n', '\\n', '\\,', '\\;'],
            $string
        );
    }
}
