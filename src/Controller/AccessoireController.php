<?php

namespace App\Controller;

use App\Entity\Accessoire;
use App\Form\AccessoireType;
use App\Repository\AccessoireRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Catalogue des accessoires (matériel mobile en stock : micros, pupitres…).
 * Réservé au super-admin : c'est la liste proposée aux usagers au moment de
 * réserver, avec la quantité disponible de chaque accessoire.
 */
#[Route('/admin/accessoire')]
#[IsGranted('ROLE_SUPER_ADMIN')]
class AccessoireController extends AbstractController
{
    #[Route('/', name: 'app_accessoire_index', methods: ['GET'])]
    public function index(AccessoireRepository $repository): Response
    {
        return $this->render('accessoire/index.html.twig', [
            'accessoires' => $repository->findBy([], ['nom' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'app_accessoire_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $accessoire = new Accessoire();
        $form = $this->createForm(AccessoireType::class, $accessoire);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($accessoire);
            $em->flush();
            $this->addFlash('success', 'Accessoire ajouté.');

            return $this->redirectToRoute('app_accessoire_index');
        }

        return $this->render('accessoire/form.html.twig', [
            'form' => $form,
            'is_edit' => false,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_accessoire_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Accessoire $accessoire, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(AccessoireType::class, $accessoire);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Accessoire modifié.');

            return $this->redirectToRoute('app_accessoire_index');
        }

        return $this->render('accessoire/form.html.twig', [
            'form' => $form,
            'is_edit' => true,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_accessoire_delete', methods: ['POST'])]
    public function delete(Request $request, Accessoire $accessoire, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$accessoire->getId(), $request->request->get('_token'))) {
            $em->remove($accessoire);
            $em->flush();
            $this->addFlash('success', 'Accessoire supprimé.');
        }

        return $this->redirectToRoute('app_accessoire_index');
    }
}
