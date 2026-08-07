<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Test de vie (health check) de l'application.
 *
 * Sonde destinée à la SUPERVISION (Splunk) et au RÉPARTITEUR DE CHARGE
 * (2 VM en préprod : 10.227.22.45 / .46). L'endpoint doit être joignable
 * SANS SSO — la sonde n'a pas de carte agent. Au niveau applicatif il est
 * déjà anonyme (aucune règle access_control ne le couvre + le rendre explicite
 * PUBLIC_ACCESS). Côté reverse-proxy Proxyma, penser à EXCLURE /health du SSO.
 *
 * Réponses :
 *   • 200 OK                → application vivante ET base joignable ;
 *   • 503 Service Unavailable → application vivante mais base injoignable.
 * Le corps JSON détaille chaque vérification (exploitable par Splunk).
 */
class HealthController extends AbstractController
{
    #[Route('/health', name: 'app_health', methods: ['GET'])]
    public function health(Connection $connection): JsonResponse
    {
        $database = 'ok';
        $status = 'ok';
        $httpCode = Response::HTTP_OK;

        // Vérification légère de la base : une requête triviale, sans I/O lourd.
        try {
            $connection->executeQuery('SELECT 1');
        } catch (\Throwable) {
            $database = 'ko';
            $status = 'degraded';
            $httpCode = Response::HTTP_SERVICE_UNAVAILABLE; // 503
        }

        return new JsonResponse([
            'status' => $status,                 // ok | degraded
            'application' => 'resadg',
            'checks' => [
                'database' => $database,         // ok | ko
            ],
            'timestamp' => (new \DateTimeImmutable())->format(\DATE_ATOM),
        ], $httpCode);
    }
}
