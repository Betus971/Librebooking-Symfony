<?php

namespace App\Service\Exception;

/**
 * Levée par {@see \App\Service\ReservationManager::createWithLock()} lorsqu'un
 * autre utilisateur a réservé le même créneau entre le pré-check et l'acquisition
 * du verrou concurrentiel (fenêtre de race fermée sous lock PostgreSQL).
 */
final class ConcurrentBookingException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('concurrent_booking');
    }
}
