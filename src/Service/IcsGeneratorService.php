<?php

namespace App\Service;

/**
 * Génère un flux iCalendar (RFC 5545) consommable par Thunderbird / Outlook /
 * Google Calendar.
 *
 * Extrait de CalendarController::feed() : le formatage et l'échappement RFC 5545
 * sont de la logique de présentation, pas du routage HTTP.
 */
final class IcsGeneratorService
{
    /**
     * Construit le document VCALENDAR complet à partir d'une liste d'événements.
     *
     * Chaque événement est un tableau :
     *   ['uid' => string, 'start' => \DateTimeInterface, 'end' => \DateTimeInterface,
     *    'summary' => string, 'description' => string (optionnel)]
     *
     * @param array<int, array{uid: string, start: \DateTimeInterface, end: \DateTimeInterface, summary: string, description?: string}> $events
     */
    public function generate(array $events = []): string
    {
        $ics  = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//Librebooking//FR\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";
        $ics .= "METHOD:PUBLISH\r\n";

        foreach ($events as $event) {
            $ics .= $this->createEvent(
                $event['uid'],
                $event['start'],
                $event['end'],
                $event['summary'],
                $event['description'] ?? '',
            );
        }

        $ics .= "END:VCALENDAR";

        return $ics;
    }

    /**
     * Génère un bloc VEVENT proprement échappé.
     */
    public function createEvent(string $uid, \DateTimeInterface $start, \DateTimeInterface $end, string $summary, string $description = ''): string
    {
        // Formatage des dates pour ICS (YmdTHis est le standard le plus sûr).
        $dtStart = $start->format('Ymd\THis');
        $dtEnd   = $end->format('Ymd\THis');
        $now     = date('Ymd\THis');

        $summary     = $this->escape($summary);
        $description = $this->escape($description);

        return "BEGIN:VEVENT\r\n" .
            "UID:{$uid}@monapp.local\r\n" .
            "DTSTAMP:{$now}\r\n" .
            "DTSTART:{$dtStart}\r\n" .
            "DTEND:{$dtEnd}\r\n" .
            "SUMMARY:{$summary}\r\n" .
            "DESCRIPTION:{$description}\r\n" .
            "END:VEVENT\r\n";
    }

    /**
     * Échappement RFC 5545 robuste contre l'injection de CRLF.
     * L'ordre est important : antislash d'abord, puis CRLF/CR/LF -> "\n", puis , et ;.
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
