<?php

namespace App\Controller;

use App\Entity\Resource;
use App\Entity\Schedule;
use App\Form\ResourceType;
use App\Repository\ResourceRepository;
use App\Repository\ScheduleRepository;
use App\Security\Voter\ResourceVoter;
use App\Service\Exception\InvalidMimeTypeException;
use App\Service\FileUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/resource')]
final class ResourceController extends AbstractController
{
    #[Route(name: 'app_resource_index', methods: ['GET'])]
    public function index(ResourceRepository $resourceRepository ,ScheduleRepository $sRepo,  Request $request ): Response
    {
        $view     = $request->query->get('view', 'cards');       // table | cards (défaut sur cards pour plus de clarté)
        $q        = trim((string)$request->query->get('q', ''));  // recherche par nom
        $schedule = $request->query->get('schedule');             // id planning
        $status   = $request->query->get('status');               // active | inactive | all
        $category = $request->query->get('category');             // NOUVEAU: id categorie

        $filters = [
            'q'        => $q ?: null,
            'schedule' => ctype_digit((string)$schedule) ? (int)$schedule : null,
            'status'   => \in_array($status, ['active','inactive','all'], true) ? $status : 'all',
            'category' => ctype_digit((string)$category) ? (int)$category : null,
        ];

        $user = $this->getUser();
        $resources = $resourceRepository->findForIndex($filters ,$user);
        $schedules = $sRepo->findBy([], ['name' => 'ASC']);

        return $this->render('resource/index.html.twig', [
            'resources' => $resources,
            'schedules' => $schedules,
            'view'      => $view,
            'filters'   => $filters,
        ]);
    }

    #[Route('/new', name: 'app_resource_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN_RESSOURCE')]
    public function new(Request $request, EntityManagerInterface $entityManager, FileUploadService $fileUploader): Response
    {
        $resource = new Resource();
        $form = $this->createForm(ResourceType::class, $resource);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // Image (champ 'photo', mapped: false) : upload centralisé + validation MIME finfo.
            $imageFile = $form->get('photo')->getData();
            if ($imageFile) {
                try {
                    $resource->setImageName(
                        $fileUploader->upload($imageFile, $this->getParameter('upload_directory'), ['image/jpeg', 'image/png', 'image/webp'])
                    );
                } catch (InvalidMimeTypeException $e) {
                    $this->addFlash('warning', 'Image rejetée (type non autorisé : ' . $e->getMimeType() . ').');
                } catch (FileException $e) {
                    $this->addFlash('warning', 'Ressource créée mais erreur lors de l\'upload de l\'image.');
                }
            }

            $entityManager->persist($resource);
            $entityManager->flush();
            $this->addFlash('success', 'Ressource créée avec succès !');
            return $this->redirectToRoute('app_resource_index', [], Response::HTTP_SEE_OTHER);
        }
        return $this->render('resource/new.html.twig', [
            'resource' => $resource,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_resource_show', methods: ['GET'])]
    public function show(Resource $resource): Response
    {
        return $this->render('resource/show.html.twig', [
            'resource' => $resource,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_resource_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Resource $resource, EntityManagerInterface $entityManager, FileUploadService $fileUploader): Response
    {
        // P3 — visibilité hybride : super-admin OU même code unité OU groupe manuel.
        // Remplace l'ancien check qui ne regardait QUE le ResourceGroup.
        $this->denyAccessUnlessGranted(ResourceVoter::MANAGE, $resource);

        $form = $this->createForm(ResourceType::class, $resource);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Image (champ 'photo', mapped: false) : même upload centralisé qu'à la création.
            $imageFile = $form->get('photo')->getData();
            if ($imageFile) {
                try {
                    // TODO (Optionnel) : supprimer l'ancienne image pour nettoyer le serveur.
                    $resource->setImageName(
                        $fileUploader->upload($imageFile, $this->getParameter('upload_directory'), ['image/jpeg', 'image/png', 'image/webp'])
                    );
                } catch (InvalidMimeTypeException $e) {
                    $this->addFlash('warning', 'Image rejetée (type non autorisé : ' . $e->getMimeType() . ').');
                } catch (FileException $e) {
                    $this->addFlash('warning', 'Modifications enregistrées, mais erreur lors du changement d\'image.');
                }
            }

                $entityManager->flush();
                $this->addFlash('success', 'Ressource mise à jour avec succès !');
                return $this->redirectToRoute('app_resource_index', [], Response::HTTP_SEE_OTHER);

        }
        return $this->render('resource/edit.html.twig', [
            'resource' => $resource,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_resource_delete', methods: ['POST'])]
    public function delete(Request $request, Resource $resource, EntityManagerInterface $entityManager): Response
    {
        // P3 — visibilité hybride (même règle que l'édition).
        $this->denyAccessUnlessGranted(ResourceVoter::MANAGE, $resource);

        if (!$this->container->has('security.csrf.token_manager') || $this->isCsrfTokenValid('delete'.$resource->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($resource);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_resource_index', [], Response::HTTP_SEE_OTHER);
    }
//    #[Route('/resource/quick-new', name: 'app_resource_quick_new',methods: ['GET', 'POST'])]
//    public function quickNew(
//        Request $request,
//        EntityManagerInterface $em,
//        SluggerInterface $slugger
//    ): Response {
//        $resource = new Resource();
//        $form = $this->createForm(ResourceQuickType::class, $resource, [
//            'attr' => ['novalidate' => 'novalidate'] // optionnel
//        ]);
//        $form->handleRequest($request);
//
//        if ($form->isSubmitted() && $form->isValid()) {
//            if ($file = $form->get('photo')->getData()) {
//                // Récupère le chemin du dossier d'upload
//                $uploadDirectory = $this->getParameter('upload_directory');
//
//                // Crée le dossier s'il n'existe pas
//                if (!file_exists($uploadDirectory)) {
//                    mkdir($uploadDirectory, 0775, true);
//                }
//
//                // Génère un nom de fichier sécurisé
//                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
//                $safeFilename = $slugger->slug($originalFilename);
//                $extension = strtolower($file->guessExtension());
//                $filename = substr($safeFilename, 0, 20) . '-' . uniqid() . '.' . $extension;
//
//                // Déplace le fichier
//                $file->move($uploadDirectory, $filename);
//                $resource->setImageName($filename);
//            }
//
//            // valeurs par défaut utiles
//            $resource->setIsActive(true)->setAutoassign(true)->setStatusId(1);
//            $resource->setDateCreated(new \DateTime());
//            $resource->setLastModified(new \DateTime());
//
//            $em->persist($resource);
//            $em->flush();
//
//            $this->addFlash('success', 'Ressource créée.');
//            return $this->redirectToRoute('app_resource_edit', ['id' => $resource->getId()]);
//        }
//
//        // Affiché dans la modale (ou page fallback)
//        return $this->render('resource/_quick_modal_content.html.twig', [
//            'form' => $form->createView(),
//        ]);
    //}

}
