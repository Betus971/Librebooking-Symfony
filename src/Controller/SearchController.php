<?php

namespace App\Controller;

use App\Repository\ResourceCategoryRepository;
use App\Repository\ResourceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SearchController extends AbstractController
{
    #[Route('/search', name: 'app_search')]
    public function index(Request $request, ResourceRepository $resourceRepo, ResourceCategoryRepository $categoryRepo): Response
    {

        // 1. On récupère les infos du formulaire
        $categoryId = $request->query->get('category'); // L'ID de la catégorie
        $dateStr = $request->query->get('date');        // La date (ex: 2024-02-06)

        // 2. On prépare la recherche
        $criteria = [];

        // Si une catégorie est choisie, on filtre. Sinon, on prend tout.
        if ($categoryId) {
            $category = $categoryRepo->find($categoryId);
            if ($category) {
                $criteria['category'] = $category;
            }
        }

        // On cherche les ressources (Salles)
        $resources = $resourceRepo->findBy($criteria);
        $displayDate = $dateStr ? $dateStr : (new \DateTime())->format('Y-m-d');
        return $this->render('search/index.html.twig', [
            'resources' => $resources,
            'searched_date' => $displayDate,
            'searched_category' => $categoryId,
        ]);
    }
}
