<?php

namespace App\Controller;

use App\Domain\Reservation\AvailabilityService;
use App\Dto\ResourceSearchCriteria;
use App\Repository\ResourceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AvailabilityController extends AbstractController
{
    /**
     * @throws \DateMalformedStringException
     */
    #[Route('/availability', name: 'app_availability')]
    public function index(Request $request, AvailabilityService $availability, ResourceRepository $resources): Response
    {

        $weekStart = new \DateTimeImmutable($request->query->get('start') ?: 'monday this week');
        $days = array_map(fn($i) => $weekStart->modify("+$i day"), range(0, 6)); // 7 jours

        // CORRECTION ICI : On convertit 'type' en entier s'il existe
        $typeParam = $request->query->get('type');
        $typeId = $typeParam ? (int) $typeParam : null;

        $criteria = new ResourceSearchCriteria(
            typeId: $typeId,
            minCapacity: (int) $request->query->get('cap')
        );


        $list = $resources->search($criteria); // À implémenter simplement (QueryBuilder)

        $categories = $resources->findAllCategories();


        // Matrice [resourceId][Y-m-d] => [ [start, end], ... ]
        $matrix = [];
        $periodStart = $days[0]->setTime(0,0);
        $periodEnd   = $days[6]->setTime(23,59,59);

        // Récup bulk des réservations / blackouts pour limiter les requêtes
        $indexedBusy = $availability->busyIndex($list, $periodStart, $periodEnd);
        foreach ($list as $r) {
            foreach ($days as $d) {
                $matrix[$r->getId()][$d->format('Y-m-d')] =
                    $availability->freeWindowsForDay($r, $d, $indexedBusy);
            }
        }
        return $this->render('availability/index.html.twig', [
            'days'      => $days,
            'resources' => $list,
            'matrix'    => $matrix,
            'weekStart' => $weekStart,
            'filters' => [
                'categories' => $categories,


            ],
        ]);
    }


    #[Route('/disponibilites/slots', name: 'app_availability_slots')]
    public function slots(Request $r, AvailabilityService $a, ResourceRepository $repo): JsonResponse
    {
        $resource = $repo->find($r->query->getInt('resource'));
        $date = new \DateTimeImmutable($r->query->get('date'));
        // TOUS les créneaux ouverts du jour, avec leur disponibilité (libre/pris).
        $list = $a->allWindowsForDay($resource, $date, $a->busyIndex([$resource], $date->setTime(0,0), $date->setTime(23,59,59)));

        $payload = array_map(fn($w) => [
            'label'     => $w->start->format('H:i') . '–' . $w->end->format('H:i'),
            'start'     => $w->start->format('H:i'),
            'end'       => $w->end->format('H:i'),
            'available' => $w->available,
        ], $list);

        return $this->json($payload);
    }

}
