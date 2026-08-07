<?php

namespace App\Presta\Controller\Admin;

use App\Presta\Entity\PrestaCategorie;
use App\Presta\Form\PrestaCategorieType;
use App\Presta\Repository\PrestaCategorieRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gestion des catégories de prestation — réservée au super-admin.
 * Permet d'en créer autant que voulu (TIR, CCPM, Coiffure, Autre…).
 */
#[Route('/presta/admin/categorie', name: 'app_presta_admin_categorie_')]
#[IsGranted('ROLE_SUPER_ADMIN')]
class CategorieController extends AbstractController
{
    public function __construct(
        private readonly PrestaCategorieRepository $categories,
    ) {
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('presta/admin/categorie/index.html.twig', [
            'categories' => $this->categories->findAllOrdered(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $categorie = new PrestaCategorie();
        $form = $this->createForm(PrestaCategorieType::class, $categorie);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->categories->save($categorie, true);
            $this->addFlash('success', 'Catégorie « ' . $categorie->getNom() . ' » créée.');

            return $this->redirectToRoute('app_presta_admin_categorie_index');
        }

        return $this->render('presta/admin/categorie/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, PrestaCategorie $categorie): Response
    {
        $form = $this->createForm(PrestaCategorieType::class, $categorie);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->categories->save($categorie, true);
            $this->addFlash('success', 'Catégorie mise à jour.');

            return $this->redirectToRoute('app_presta_admin_categorie_index');
        }

        return $this->render('presta/admin/categorie/edit.html.twig', [
            'form'      => $form,
            'categorie' => $categorie,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, PrestaCategorie $categorie): Response
    {
        if ($this->isCsrfTokenValid('delete_presta_categorie_' . $categorie->getId(), $request->request->get('_token'))) {
            // Les prestations liées passent à categorie_id = NULL (ON DELETE SET NULL).
            $this->categories->remove($categorie, true);
            $this->addFlash('success', 'Catégorie supprimée.');
        }

        return $this->redirectToRoute('app_presta_admin_categorie_index');
    }
}
