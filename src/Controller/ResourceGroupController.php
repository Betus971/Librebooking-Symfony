<?php

namespace App\Controller;
use App\Entity\ResourceGroup;
use App\Form\ResourceGroupType;
use App\Repository\ResourceGroupRepository;
use App\Repository\UserRepository;
use App\Security\Voter\ResourceGroupVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/resource-group')]
#[IsGranted('ROLE_ADMIN_RESSOURCE')]
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
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function new(Request $request, EntityManagerInterface $entityManager, UserRepository $users): Response
    {
        $resourceGroup = new ResourceGroup();
        $form = $this->createForm(ResourceGroupType::class, $resourceGroup);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->syncPeople($resourceGroup, $request, $users, withAdmins: true);
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
    public function edit(Request $request, ResourceGroup $resourceGroup, EntityManagerInterface $entityManager, UserRepository $users): Response
    {
        // Super-admin OU administrateur désigné de CE groupe.
        $this->denyAccessUnlessGranted(ResourceGroupVoter::MANAGE, $resourceGroup);

        $form = $this->createForm(ResourceGroupType::class, $resourceGroup);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Les membres et ressources sont gérés par tout admin du groupe.
            // La liste des ADMINISTRATEURS n'est modifiable que par le super-admin
            // (évite l'auto-promotion / la perte de contrôle).
            $this->syncPeople($resourceGroup, $request, $users, withAdmins: $this->isGranted('ROLE_SUPER_ADMIN'));
            $entityManager->flush();

            $this->addFlash('success', 'L\'équipe a été modifiée avec succès.');
            return $this->redirectToRoute('app_resource_group_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('resource_group/edit.html.twig', [
            'resource_group' => $resourceGroup,
            'form' => $form,
        ]);
    }

    /**
     * Synchronise les membres (member_ids[]) et, si autorisé, les administrateurs
     * (admin_ids[]) du groupe à partir des ids soumis par les widgets.
     */
    private function syncPeople(ResourceGroup $group, Request $request, UserRepository $users, bool $withAdmins): void
    {
        // --- Membres ---
        $memberIds = array_filter(array_map('intval', (array) $request->request->all('member_ids')));
        foreach ($group->getUsers()->toArray() as $current) {
            if (!in_array($current->getId(), $memberIds, true)) {
                $group->removeUser($current);
            }
        }
        if ($memberIds !== []) {
            foreach ($users->findBy(['id' => $memberIds]) as $user) {
                $group->addUser($user);
            }
        }

        // --- Administrateurs du groupe (super-admin uniquement) ---
        if ($withAdmins) {
            $adminIds = array_filter(array_map('intval', (array) $request->request->all('admin_ids')));
            foreach ($group->getAdmins()->toArray() as $current) {
                if (!in_array($current->getId(), $adminIds, true)) {
                    $group->removeAdmin($current);
                }
            }
            if ($adminIds !== []) {
                foreach ($users->findBy(['id' => $adminIds]) as $user) {
                    $group->addAdmin($user);
                }
            }
        }
    }

    #[Route('/{id}', name: 'app_resource_group_delete', methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
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
