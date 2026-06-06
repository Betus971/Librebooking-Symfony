<?php

namespace App\Controller;

use App\Domain\Reservation\AvailabilityService;
use App\Entity\ResourceCategory;
use App\Form\ResourceCategoryType;
use App\Repository\ResourceCategoryRepository;
use App\Repository\ResourceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/resource/category')]
final class ResourceCategoryController extends AbstractController
{
    #[Route(name: 'app_resource_category_index', methods: ['GET'])]
    public function index(ResourceCategoryRepository $resourceCategoryRepository): Response
    {
        return $this->render('resource_category/index.html.twig', [
            'resource_categories' => $resourceCategoryRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_resource_category_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $resourceCategory = new ResourceCategory();
        $form = $this->createForm(ResourceCategoryType::class, $resourceCategory);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $entityManager->persist($resourceCategory);
            $entityManager->flush();




            $this->addFlash('success', 'Catégorie créée.');

            return $this->redirectToRoute('app_resource_category_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('resource_category/new.html.twig', [
            'resource_category' => $resourceCategory,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_resource_category_show', methods: ['GET'])]
    public function show(ResourceCategory $resourceCategory , ResourceRepository $repo , Request $request,
                         AvailabilityService $availability): Response
    {
        $resources = $repo->findActiveByCategory($resourceCategory);


        // 2. Date sélectionnée (défaut = aujourd'hui)
        $dateParam    = $request->query->get('date', (new \DateTimeImmutable())->format('Y-m-d'));
        $selectedDate = new \DateTimeImmutable($dateParam);

        // 3. Carte disponibilité  [resourceId => bool]
        $dayStart       = $selectedDate->setTime(0, 0, 0);
        $dayEnd         = $selectedDate->setTime(23, 59, 59);
        $indexedBusy    = $availability->busyIndex($resources, $dayStart, $dayEnd);
        $availabilityMap = [];

        foreach ($resources as $r) {
            $windows = $availability->freeWindowsForDay($r, $selectedDate, $indexedBusy);
            $availabilityMap[$r->getId()] = count($windows) > 0;
        }

        return $this->render('resource_category/show.html.twig', [
            'resource_category' => $resourceCategory,
            'resources' => $resources,
            'selectedDate'       => $selectedDate->format('Y-m-d'),
            'availabilityMap'    => $availabilityMap,


        ]);
    }

    #[Route('/{id}/edit', name: 'app_resource_category_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ResourceCategory $resourceCategory, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ResourceCategoryType::class, $resourceCategory);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
        $entityManager->flush();
        $this->addFlash('success', 'Catégorie mise à jour.');


            return $this->redirectToRoute('app_resource_category_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('resource_category/edit.html.twig', [
            'resource_category' => $resourceCategory,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_resource_category_delete', methods: ['POST'])]
    public function delete(Request $request, ResourceCategory $resourceCategory, EntityManagerInterface $entityManager): Response
    {
        if (!$this->container->has('security.csrf.token_manager') || $this->isCsrfTokenValid('delete'.$resourceCategory->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($resourceCategory);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_resource_category_index', [], Response::HTTP_SEE_OTHER);
    }
}
