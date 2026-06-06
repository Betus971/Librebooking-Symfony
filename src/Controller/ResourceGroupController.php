<?php

namespace App\Controller;
use App\Entity\ResourceGroup;
use App\Form\ResourceGroupType;
use App\Repository\ResourceGroupRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/resource-group')]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class ResourceGroupController extends AbstractController
{
    #[Route('/', name: 'app_resource_group_index')]
    public function index(ResourceGroupRepository $resourceGroupRepository): Response
    {

        return $this->render('resource_group/index.html.twig', [
            'resource_groups' => $resourceGroupRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_resource_group_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $resourceGroup = new ResourceGroup();
        $form = $this->createForm(ResourceGroupType::class, $resourceGroup);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($resourceGroup);
            $entityManager->flush();

            $this->addFlash('success', 'L\'équipe a été créée avec succès.');
            return $this->redirectToRoute('app_resource_group_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('resource_group/new.html.twig', [
            'resource_group' => $resourceGroup,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_resource_group_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ResourceGroup $resourceGroup, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ResourceGroupType::class, $resourceGroup);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'L\'équipe a été modifiée avec succès.');
            return $this->redirectToRoute('app_resource_group_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('resource_group/edit.html.twig', [
            'resource_group' => $resourceGroup,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_resource_group_delete', methods: ['POST'])]
    public function delete(Request $request, ResourceGroup $resourceGroup, EntityManagerInterface $entityManager): Response
    {
        if (!$this->container->has('security.csrf.token_manager') || $this->isCsrfTokenValid('delete'.$resourceGroup->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($resourceGroup);
            $entityManager->flush();
            $this->addFlash('success', 'L\'équipe a été supprimée.');
        }

        return $this->redirectToRoute('app_resource_group_index', [], Response::HTTP_SEE_OTHER);
    }
}
