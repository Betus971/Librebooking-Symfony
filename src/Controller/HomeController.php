<?php

namespace App\Controller;

use App\Repository\AnnouncementRepository;
use App\Repository\ReservationSeriesRepository;
use App\Repository\ResourceCategoryRepository;
use App\Repository\ResourceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        ResourceRepository $repo,
        ResourceCategoryRepository $catRepo,
        AnnouncementRepository $announcementRepository,
        ReservationSeriesRepository $reservationRepo
    ): Response {
        $announcements = $announcementRepository->findActiveAnnouncements();

        // 2. Tes Catégories (Inchangé)
        $rows = $catRepo->findForHome();
        $categories = array_map(fn ($r) => [
            'category' => $r[0],
            'count'    => (int)$r['resourceCount'],
        ], $rows);

        // 3. 👇 NOUVEAU : La prochaine réservation de l'user connecté
        $nextReservation = null;
        if ($this->getUser()) {
            $nextReservation = $reservationRepo->findNextForUser($this->getUser());
        }

        return $this->render('home/index.html.twig', [
//            'resource' => $resources,  // ✅ clef attendue par le Twig
            'categories' => $categories,
            'announcements' => $announcements,
            'next_reservation' => $nextReservation,


        ]);
    }
}
