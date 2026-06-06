<?php

namespace App\Controller;

use App\Entity\BlackoutInstance;
use App\Entity\BlackoutSeries;
use App\Form\BlackoutSeriesType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/blackout/series')]
final class BlackoutSeriesController extends AbstractController
{
    #[Route(name: 'app_blackout_series_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $blackoutSeries = $entityManager
            ->getRepository(BlackoutSeries::class)
            ->findAll();

        return $this->render('blackout_series/index.html.twig', [
            'blackout_series' => $blackoutSeries,
        ]);
    }

    #[Route('/new', name: 'app_blackout_series_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager , ): Response
    {
        $blackoutSeries = new BlackoutSeries();
        // On définit le propriétaire (Toi, l'admin connecté)/
         $blackoutSeries->setOwner($this->getUser());

        $form = $this->createForm(BlackoutSeriesType::class, $blackoutSeries);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // 1. On prépare la Série (Le titre et la ressource sont déjà remplis par le form)
            $blackoutSeries->setDateCreated(new \DateTime());

            // 2. On récupère les dates du formulaire "virtuel"
            $beginDate = $form->get('start')->getData();
            $endDate = $form->get('end')->getData();

            // 3. On CRÉE L'INSTANCE automatiquement 🪄
            $instance = new BlackoutInstance();
            $instance->setStartDate($beginDate);
            $instance->setEndDate($endDate);

            // 4. On relie les deux
            $instance->setSeries($blackoutSeries);
            $blackoutSeries->addInstance($instance); // Si tu as cette méthode dans l'entité

            // 5. On enregistre tout
            $entityManager->persist($blackoutSeries);
            $entityManager->persist($instance);
            $entityManager->flush();

            return $this->redirectToRoute('app_blackout_series_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('blackout_series/new.html.twig', [
            'blackout_series' => $blackoutSeries,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_blackout_series_show', methods: ['GET'])]
    public function show(BlackoutSeries $blackoutSeries): Response
    {
        return $this->render('blackout_series/show.html.twig', [
            'blackout_series' => $blackoutSeries,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_blackout_series_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, BlackoutSeries $blackoutSeries, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(BlackoutSeriesType::class, $blackoutSeries);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_blackout_series_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('blackout_series/edit.html.twig', [
            'blackout_series' => $blackoutSeries,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_blackout_series_delete', methods: ['POST'])]
    public function delete(Request $request, BlackoutSeries $blackoutSeries, EntityManagerInterface $entityManager): Response
    {
        if (!$this->container->has('security.csrf.token_manager') || $this->isCsrfTokenValid('delete'.$blackoutSeries->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($blackoutSeries);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_blackout_series_index', [], Response::HTTP_SEE_OTHER);
    }
}
