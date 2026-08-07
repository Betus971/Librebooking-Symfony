<?php

namespace App\Controller;

use App\Entity\Layout;

use App\Form\LayoutType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/layout')]
final class LayoutController extends AbstractController
{
    #[Route(name: 'app_layout_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $layouts = $entityManager
            ->getRepository(Layout::class)
            ->findAll();

        return $this->render('layout/index.html.twig', [
            'layouts' => $layouts,
        ]);
    }

    #[Route('/new', name: 'app_layout_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $layout = new Layout();
        $form = $this->createForm(LayoutType::class, $layout);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($layout);
            $entityManager->flush();

            return $this->redirectToRoute('app_layout_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('layout/new.html.twig', [
            'layout' => $layout,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_layout_show', methods: ['GET'])]
    public function show(Layout $layout): Response
    {
        return $this->render('layout/show.html.twig', [
            'layout' => $layout,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_layout_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Layout $layout, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(LayoutType::class, $layout);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_layout_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('layout/edit.html.twig', [
            'layout' => $layout,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_layout_delete', methods: ['POST'])]
    public function delete(Request $request, Layout $layout, EntityManagerInterface $entityManager): Response
    {
        if (!$this->container->has('security.csrf.token_manager') || $this->isCsrfTokenValid('delete'.$layout->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($layout);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_layout_index', [], Response::HTTP_SEE_OTHER);
    }
}
