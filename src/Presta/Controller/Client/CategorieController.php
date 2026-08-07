<?php

namespace App\Presta\Controller\Client;

use App\Presta\Entity\PrestaCategorie;
use App\Presta\Repository\PrestaCategorieRepository;
use App\Presta\Repository\ServiceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Parcours client PAR CATÉGORIE : l'usager choisit une catégorie (TIR, CCPM,
 * Coiffure…) puis voit toutes les prestations de cette catégorie, tous
 * prestataires confondus. Remplace l'entrée « par prestataire » comme point
 * d'accès principal à la prise de rendez-vous.
 */
#[Route('/presta/c/categorie', name: 'app_presta_client_categorie_')]
class CategorieController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(PrestaCategorieRepository $categories): Response
    {
        return $this->render('presta/client/categorie/index.html.twig', [
            'categories' => $categories->findAllOrdered(),
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(PrestaCategorie $categorie, ServiceRepository $services): Response
    {
        return $this->render('presta/client/categorie/show.html.twig', [
            'categorie' => $categorie,
            'services'  => $services->findActiveByCategorie($categorie),
        ]);
    }
}
