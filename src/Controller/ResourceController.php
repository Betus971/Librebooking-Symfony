<?php

namespace App\Controller;

use App\Entity\Resource;
use App\Entity\Schedule;
use App\Form\ResourceType;
use App\Repository\ResourceRepository;
use App\Repository\ScheduleRepository;
use App\Security\Voter\ResourceVoter;
use App\Service\Exception\InvalidFileTypeException;
use App\Service\FileUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/resource')]
final class ResourceController extends AbstractController
{
    /**
     * Endpoint JSON utilisé par le widget « Horaires d'ouverture » sur le
     * formulaire de réservation (demande MOA juin 2026 : aider l'utilisateur
     * à savoir QUAND il peut réserver, sans devoir essayer puis lire l'erreur).
     *
     * Réponse :
     *   { "minTime": "08:00", "maxTime": "17:00", "daysLabel": "Lundi à vendredi" }
     *   ou { "available": false } si la ressource n'a pas de planning.
     */
    #[Route('/{id}/opening-hours.json', name: 'app_resource_opening_hours_json', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function openingHoursJson(Resource $resource): JsonResponse
    {
        $schedule = $resource->getSchedule();
        $layout   = $schedule?->getLayout();
        $summary  = $layout?->getOpeningSummary();

        if (null === $summary) {
            return $this->json(['available' => false]);
        }

        return $this->json([
            'available' => true,
            'resource'  => $resource->getName(),
            'minTime'   => $summary['minTime'],
            'maxTime'   => $summary['maxTime'],
            'daysLabel' => $summary['daysLabel'],
            'daysOpen'  => $summary['daysOpen'],
            'scheduleStart' => $schedule?->getStartDate()?->format('Y-m-d'),
            'scheduleEnd'   => $schedule?->getEndDate()?->format('Y-m-d'),
        ]);
    }

    #[Route(name: 'app_resource_index', methods: ['GET'])]
    public function index(ResourceRepository $resourceRepository ,ScheduleRepository $sRepo,  Request $request ): Response
    {
        $view     = $request->query->get('view', 'table');       // table | cards
        $q        = trim((string)$request->query->get('q', ''));  // recherche par nom
        $schedule = $request->query->get('schedule');             // id planning
        $status   = $request->query->get('status');               // active | inactive | all

        $filters = [
            'q'        => $q ?: null,
            'schedule' => ctype_digit((string)$schedule) ? (int)$schedule : null,
            'status'   => \in_array($status, ['active','inactive','all'], true) ? $status : 'all',
        ];

        $user = $this->getUser();
        $resources = $resourceRepository->findForIndex($filters ,$user); // méthode ci-dessous
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
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger, FileUploadService $uploader): Response
    {
        $resource = new Resource();
        $form = $this->createForm(ResourceType::class, $resource);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
        // --- 📸 LOGIQUE IMAGE (RF-13 : déléguée à FileUploadService) ---
        // On récupère le fichier depuis le champ 'photo' (mapped: false)
        $imageFile = $form->get('photo')->getData();

        if ($imageFile) {
            // P0.6 — validation MIME réelle (finfo) AVANT déplacement : bloque
            // les polyglots (ex. PHP déguisé en .png) malgré le durcissement htaccess.
            try {
                $newFilename = $uploader->upload(
                    $imageFile,
                    $this->getParameter('upload_directory'),
                    ['image/jpeg', 'image/png', 'image/webp']
                );
                // On enregistre le NOM du fichier dans la base de données
                $resource->setImageName($newFilename);
            } catch (InvalidFileTypeException $e) {
                $this->addFlash('warning', 'Image rejetée (type non autorisé : ' . $e->getDetectedMime() . ').');
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
    public function edit(Request $request, Resource $resource, EntityManagerInterface $entityManager, SluggerInterface $slugger, FileUploadService $uploader): Response
    {
        // P3 — visibilité hybride : super-admin OU même code unité OU groupe manuel.
        // Remplace l'ancien check qui ne regardait QUE le ResourceGroup.
        $this->denyAccessUnlessGranted(ResourceVoter::MANAGE, $resource);

        $form = $this->createForm(ResourceType::class, $resource);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // --- 📸 LOGIQUE IMAGE (RF-13 : déléguée à FileUploadService) ---
            $imageFile = $form->get('photo')->getData();

            if ($imageFile) {
                // P0.6 — même validation finfo qu'à la création.
                try {
                    $newFilename = $uploader->upload(
                        $imageFile,
                        $this->getParameter('upload_directory'),
                        ['image/jpeg', 'image/png', 'image/webp']
                    );

                    // TODO (Optionnel) : Ici tu pourrais supprimer l'ancienne image pour nettoyer le serveur
                    // if ($resource->getImageName()) { ... unlink ... }

                    $resource->setImageName($newFilename);
                } catch (InvalidFileTypeException $e) {
                    $this->addFlash('warning', 'Image rejetée (type non autorisé : ' . $e->getDetectedMime() . ').');
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
