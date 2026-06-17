<?php

namespace App\Controller\Api;

use App\Entity\Resource;
use App\Repository\ReservationInstanceRepository;
use App\Repository\ResourceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api', name: 'api_')]
// P0.5 — L'API planning exposait l'agenda complet de l'organisation sans aucune
// authentification (aucune règle access_control ne couvre ^/api). On exige au
// minimum un utilisateur authentifié.
#[IsGranted('ROLE_USER')]
final class PlanningController extends AbstractController
{
    #[Route('/planning', name: 'planning', methods: ['GET'])]
    public function planning(Request $request, ResourceRepository $resourceRepo, ReservationInstanceRepository $instances): JsonResponse
    {
        // --- Params ---
        $fromStr = $request->query->get('from');            // YYYY-MM-DD
        $days    = (int) $request->query->get('days', 7);   // 1..31
        $typeId  = $request->query->getInt('typeId', 0);    // id de catégorie Ressource (optionnel)

        if ($days < 1) {
            $days = 1;
        }
        if ($days > 31) {
            $days = 31;
        }

        // from = lundi de la semaine si vide
        if (!$fromStr) {
            $now = new \DateTimeImmutable('today');
            $dow = (int) $now->format('N'); // 1=lundi..7=dimanche
            $from = $now->modify('-' . ($dow - 1) . ' days');
        } else {
            $from = \DateTimeImmutable::createFromFormat('Y-m-d', $fromStr) ?: new \DateTimeImmutable('today');
        }
        $from = $from->setTime(0, 0, 0);
        $to   = $from->modify('+' . $days . ' days');

        // --- Ressources (filtre catégorie éventuel) ---
        $resources = $resourceRepo->findForPlanning($typeId);

        if (!$resources) {
            return $this->json([
                'resources' => [],
                'bookings'  => [],
                'range'     => ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')],
            ], 200, [], ['json_encode_options' => \JSON_UNESCAPED_UNICODE]);
        }

        $resIds = array_map(fn (Resource $r) => $r->getId(), $resources);

        // --- Réservations chevauchant la plage (requête encapsulée côté repository) ---
        $bookings = $instances->findForPlanningRange($resIds, $from, $to);

        // --- Sérialisation ressources ---
        $outResources = array_map(function (Resource $r) {
            $name = method_exists($r, 'getName')
                ? $r->getName()
                : (method_exists($r, 'getNom') ? $r->getNom() : ('Resource #' . $r->getId()));

            return [
                'id'       => $r->getId(),
                'name'     => $name,
                'type'     => $r->getCategory()?->getName(),
                'typeId'   => $r->getCategory()?->getId(),
                'capacity' => method_exists($r, 'getCapacity') ? $r->getCapacity() : null,
            ];
        }, $resources);

        // --- Sérialisation bookings ---
        $resourceIdSet = array_flip($resIds);
        $outBookings = [];

        foreach ($bookings as $ri) {
            $start  = $ri->getStartDate()->format('c');
            $end    = $ri->getEndDate()->format('c');

            $title  = method_exists($ri, 'getTitle')
                ? (string) $ri->getTitle()
                : (method_exists($ri, 'getSeries') && method_exists($ri->getSeries(), 'getTitle')
                    ? (string) $ri->getSeries()->getTitle()
                    : '');

            $status = method_exists($ri, 'getStatus')
                ? (string) $ri->getStatus()
                : 'reserved';

            // pour chaque ressource rattachée via la série
            foreach ($ri->getSeries()->getReservationResources() as $link) {
                $res = $link->getResource();
                if (!$res) {
                    continue;
                }
                $rid = $res->getId();
                if (!isset($resourceIdSet[$rid])) {
                    continue;
                } // filtrage cohérent avec la liste

                $outBookings[] = [
                    'resourceId' => $rid,
                    'start'      => $start,
                    'end'        => $end,
                    'title'      => $title,
                    'status'     => $status,
                ];
            }
        }

        return $this->json([
            'resources' => $outResources,
            'bookings'  => $outBookings,
            'range'     => ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')],
        ], 200, [], ['json_encode_options' => \JSON_UNESCAPED_UNICODE]);
    }
}
