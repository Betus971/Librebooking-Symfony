<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\ReservationInstanceRepository;
use App\Repository\ResourceCategoryRepository;
use App\Service\PdfGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

/**
 * Statistiques d'usage des RESSOURCES, réservées à un gestionnaire de ressources.
 *
 * Périmètre (scope de visibilité hybride, P3) : un gestionnaire ne voit que les
 * statistiques des ressources qu'il gère, c'est-à-dire celles portant son code
 * unité (couche SSO automatique) OU appartenant à un de ses ResourceGroup
 * (couche manuelle). Un super-admin voit toutes les ressources.
 */
final class StatistiqueRessourceController extends AbstractController
{
    #[Route('/statistique/ressources', name: 'app_statistique_ressources', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN_RESSOURCE')]
    public function index(
        Request $request,
        ReservationInstanceRepository $instanceRepository,
        ResourceCategoryRepository $categoryRepository,
        ChartBuilderInterface $chartBuilder
    ): Response {
        // --- Filtres (période + catégorie), défaut = année en cours ---
        $currentYear = (int) date('Y');
        $startParam = $request->query->get('start_date');
        $endParam   = $request->query->get('end_date');
        $catParam   = $request->query->get('category_id');
        $categoryId = ($catParam !== null && $catParam !== '') ? (int) $catParam : null;

        try {
            $startDate = $startParam ? new \DateTime($startParam . ' 00:00:00') : new \DateTime($currentYear . '-01-01 00:00:00');
        } catch (\Exception) {
            $startDate = new \DateTime($currentYear . '-01-01 00:00:00');
        }
        try {
            $endDate = $endParam ? new \DateTime($endParam . ' 23:59:59') : new \DateTime($currentYear . '-12-31 23:59:59');
        } catch (\Exception) {
            $endDate = new \DateTime($currentYear . '-12-31 23:59:59');
        }
        if ($startDate > $endDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        // --- Construction du scope (même logique que AdminReservationController) ---
        $scope = [];
        if (!$this->isGranted('ROLE_SUPER_ADMIN')) {
            $scope['scoped'] = true;

            /** @var User|null $user */
            $user = $this->getUser();
            $groupIds = [];
            if ($user instanceof User) {
                foreach ($user->getResourceGroups() as $g) {
                    if (null !== $g->getId()) {
                        $groupIds[] = $g->getId();
                    }
                }
            }
            $scope['resourceGroupIds'] = $groupIds;
            $scope['scopeCodeUnite']   = $user instanceof User ? $user->getCodeunite() : null;
        }

        $stats = $instanceRepository->resourceUsageStats($scope, $startDate, $endDate, $categoryId);

        // --- Indicateurs clés ---
        $totalReservations = array_sum(array_column($stats, 'count'));
        $totalHeures       = round(array_sum(array_column($stats, 'hours')), 1);
        $nbRessources      = count($stats);

        // --- Graphique : top 10 ressources par nombre de réservations ---
        $top = array_slice($stats, 0, 10);
        $chartTop = $chartBuilder->createChart(Chart::TYPE_BAR);
        $chartTop->setData([
            'labels' => array_map(static fn (array $r) => $r['name'], $top),
            'datasets' => [[
                'label' => 'Nombre de réservations',
                'backgroundColor' => '#000091', // Bleu France DSFR
                'data' => array_map(static fn (array $r) => $r['count'], $top),
            ]],
        ]);
        $chartTop->setOptions([
            'indexAxis' => 'y', // barres horizontales : noms de ressources lisibles
            'plugins' => ['legend' => ['display' => false], 'title' => ['display' => true, 'text' => 'Top ressources les plus réservées']],
            'scales' => ['x' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
        ]);

        return $this->render('statistique/ressources.html.twig', [
            'stats'             => $stats,
            'totalReservations' => $totalReservations,
            'totalHeures'       => $totalHeures,
            'nbRessources'      => $nbRessources,
            'chartTop'          => $chartTop,
            'categories'        => $categoryRepository->findBy([], ['name' => 'ASC']),
            'filters'           => [
                'start_date'  => $startDate->format('Y-m-d'),
                'end_date'    => $endDate->format('Y-m-d'),
                'category_id' => $categoryId,
            ],
        ]);
    }

    /**
     * Export PDF des statistiques ressources. Graphique capturé côté navigateur
     * (canvas → PNG) ; indicateurs et tableau RECALCULÉS côté serveur, avec le
     * même périmètre de visibilité (scope) que l'écran.
     */
    #[Route('/statistique/ressources/pdf', name: 'app_statistique_ressources_pdf', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN_RESSOURCE')]
    public function exportPdf(
        Request $request,
        ReservationInstanceRepository $instanceRepository,
        PdfGenerator $pdf
    ): Response {
        if (!$this->isCsrfTokenValid('stat_res_pdf', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide.');
        }

        [$scope, $startDate, $endDate, $categoryId] = $this->resolveScopeAndPeriod($request);

        $stats             = $instanceRepository->resourceUsageStats($scope, $startDate, $endDate, $categoryId);
        $totalReservations = array_sum(array_column($stats, 'count'));
        $totalHeures       = round(array_sum(array_column($stats, 'hours')), 1);
        $nbRessources      = count($stats);

        $html = $this->renderView('statistique/pdf/ressources.html.twig', [
            'genereLe'          => new \DateTime(),
            'startDate'         => $startDate,
            'endDate'           => $endDate,
            'totalReservations' => $totalReservations,
            'totalHeures'       => $totalHeures,
            'nbRessources'      => $nbRessources,
            'stats'             => $stats,
            'imgTop'            => $pdf->sanitizePngDataUri($request->request->get('chart_top')),
        ]);

        return $pdf->inlinePdf($html, 'statistiques-ressources');
    }

    /**
     * Rejoue les filtres (période + catégorie) et le scope de visibilité, comme
     * dans index() — pour que le PDF reflète exactement ce que voit l'utilisateur.
     *
     * @return array{0:array<string,mixed>,1:\DateTime,2:\DateTime,3:?int}
     */
    private function resolveScopeAndPeriod(Request $request): array
    {
        $currentYear = (int) date('Y');
        $startParam  = $request->get('start_date');
        $endParam    = $request->get('end_date');
        $catParam    = $request->get('category_id');
        $categoryId  = ($catParam !== null && $catParam !== '') ? (int) $catParam : null;

        try {
            $startDate = $startParam ? new \DateTime($startParam . ' 00:00:00') : new \DateTime($currentYear . '-01-01 00:00:00');
        } catch (\Exception) {
            $startDate = new \DateTime($currentYear . '-01-01 00:00:00');
        }
        try {
            $endDate = $endParam ? new \DateTime($endParam . ' 23:59:59') : new \DateTime($currentYear . '-12-31 23:59:59');
        } catch (\Exception) {
            $endDate = new \DateTime($currentYear . '-12-31 23:59:59');
        }
        if ($startDate > $endDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $scope = [];
        if (!$this->isGranted('ROLE_SUPER_ADMIN')) {
            $scope['scoped'] = true;
            /** @var User|null $user */
            $user = $this->getUser();
            $groupIds = [];
            if ($user instanceof User) {
                foreach ($user->getResourceGroups() as $g) {
                    if (null !== $g->getId()) {
                        $groupIds[] = $g->getId();
                    }
                }
            }
            $scope['resourceGroupIds'] = $groupIds;
            $scope['scopeCodeUnite']   = $user instanceof User ? $user->getCodeunite() : null;
        }

        return [$scope, $startDate, $endDate, $categoryId];
    }
}
