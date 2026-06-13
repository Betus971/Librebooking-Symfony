<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Ajoute les en-têtes de sécurité HTTP sur chaque réponse (durcissement P2.8).
 *
 * Objectif : défense en profondeur contre les attaques par injection côté
 * navigateur (XSS, clickjacking, MIME-sniffing) — en complément de
 * l'échappement Twig et de la protection CSRF déjà en place côté formulaires.
 *
 * Choix de CSP : application en réseau fermé (air-gap), toutes les ressources
 * sont auto-hébergées → `default-src 'self'` bloque tout chargement externe
 * (aucun script/style/image tiers ne peut être injecté). On autorise
 * `'unsafe-inline'` sur script/style car le DSFR et plusieurs vues utilisent
 * des styles et scripts inline ; passer à des nonces est une amélioration
 * future (cf. docs/07).
 */
final class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        // Priorité négative : on passe après les autres listeners de réponse.
        return [KernelEvents::RESPONSE => ['onKernelResponse', -10]];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        // On ne durcit que la requête principale (pas les sous-requêtes/ESI).
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Ne pas casser le profiler / la web debug toolbar en dev.
        $path = $request->getPathInfo();
        if (str_starts_with($path, '/_profiler') || str_starts_with($path, '/_wdt')) {
            return;
        }

        $headers = $event->getResponse()->headers;

        // Anti-clickjacking (doublé par frame-ancestors dans la CSP).
        $headers->set('X-Frame-Options', 'DENY');
        // Empêche le navigateur de "deviner" un type MIME (anti-sniffing).
        $headers->set('X-Content-Type-Options', 'nosniff');
        // Limite la fuite de l'URL référente vers l'extérieur.
        $headers->set('Referrer-Policy', 'same-origin');
        // Désactive quelques API navigateur sensibles par défaut.
        $headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        // Content-Security-Policy — cœur de la défense anti-XSS.
        if (!$headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline'",
                "style-src 'self' 'unsafe-inline'",
                "img-src 'self' data:",
                "font-src 'self' data:",
                "connect-src 'self'",
                "frame-ancestors 'none'",
                "base-uri 'self'",
                "form-action 'self'",
                "object-src 'none'",
            ]));
        }
    }
}
