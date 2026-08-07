<?php

namespace App\Controller;

use App\Domain\Reservation\AvailabilityService;
use App\Entity\ResourceCategory;
use App\Form\ResourceCategoryType;
use App\Repository\ResourceCategoryRepository;
use App\Repository\ResourceRepository;
use App\Service\AvailabilityChecker;
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
                         AvailabilityService $availability,
                         AvailabilityChecker $checker): Response
    {
        $resources = $repo->findActiveByCategory($resourceCategory);

        // 1. Dates sélectionnées (défaut date début = aujourd'hui).
        //    Date de fin facultative : si absente ou égale à start, on est en
        //    "single-day". Sinon, on cherche sur la plage [start, end].
        $dateParam      = $request->query->get('date',     (new \DateTimeImmutable())->format('Y-m-d'));
        $dateEndParam   = $request->query->get('date_end');
        $selectedDate   = new \DateTimeImmutable($dateParam);
        $selectedDateEnd = ($dateEndParam && $dateEndParam !== $dateParam)
            ? new \DateTimeImmutable($dateEndParam)
            : null;

        // 2. Créneau horaire optionnel (demande MOA juin 2026) :
        //    si start/end sont fournis, on filtre les salles disponibles sur
        //    CE CRÉNEAU PRÉCIS (et pas juste « libre au moins une fois dans
        //    la journée »). Sinon comportement initial = jour entier.
        $startTimeRaw = $request->query->get('start_time');
        $endTimeRaw   = $request->query->get('end_time');

        $hasSlot = $startTimeRaw !== null && $endTimeRaw !== null
            && preg_match('/^\d{2}:\d{2}$/', (string) $startTimeRaw)
            && preg_match('/^\d{2}:\d{2}$/', (string) $endTimeRaw)
            && $startTimeRaw < $endTimeRaw;

        $availabilityMap = [];

        if ($hasSlot) {
            // ─── Cas A : créneau horaire saisi (avec ou sans date de fin) ──
            //    Vérification "salle libre PILE sur ce créneau" via le checker
            //    qui gère déjà single-day vs multi-day, conflits et heures
            //    d'ouverture (cf. §1sexies du journal).
            [$h1, $m1] = explode(':', (string) $startTimeRaw);
            [$h2, $m2] = explode(':', (string) $endTimeRaw);

            $start       = $selectedDate->setTime((int) $h1, (int) $m1, 0);
            $endDateBase = $selectedDateEnd ?? $selectedDate;
            $end         = $endDateBase->setTime((int) $h2, (int) $m2, 0);

            foreach ($resources as $r) {
                $availabilityMap[$r->getId()] = $checker->isFree($r, $start, $end);
            }
        } elseif ($selectedDateEnd !== null) {
            // ─── Cas B : plage de dates SANS créneau horaire ────────────────
            //    Sémantique métier : "au moins un créneau libre CHAQUE jour
            //    de la plage". On ne peut PAS utiliser isFree(start_du_jour=00:00,
            //    end_du_jour=23:59) parce qu'aucun TimeBlock ne couvre 24h/24.
            //    On itère donc avec freeWindowsForDay (comme le cas historique
            //    single-day) en marquant KO toute ressource qui rate UN jour.
            $resourcesStatus = [];
            foreach ($resources as $r) {
                $resourcesStatus[$r->getId()] = true; // optimiste, on invalide en cours de route
            }

            // busyIndex global sur toute la période pour limiter les requêtes
            $periodStart = $selectedDate->setTime(0, 0, 0);
            $periodEnd   = $selectedDateEnd->setTime(23, 59, 59);
            $indexedBusy = $availability->busyIndex($resources, $periodStart, $periodEnd);

            $cursor = $selectedDate;
            while ($cursor <= $selectedDateEnd) {
                foreach ($resources as $r) {
                    if (!$resourcesStatus[$r->getId()]) {
                        continue; // déjà invalide sur un jour précédent
                    }
                    $windows = $availability->freeWindowsForDay($r, $cursor, $indexedBusy);
                    if (count($windows) === 0) {
                        $resourcesStatus[$r->getId()] = false;
                    }
                }
                $cursor = $cursor->modify('+1 day');
            }
            $availabilityMap = $resourcesStatus;
        } else {
            // ─── Cas C : date unique sans créneau (comportement historique) ─
            //    "au moins un créneau libre dans la journée"
            $dayStart    = $selectedDate->setTime(0, 0, 0);
            $dayEnd      = $selectedDate->setTime(23, 59, 59);
            $indexedBusy = $availability->busyIndex($resources, $dayStart, $dayEnd);

            foreach ($resources as $r) {
                $windows = $availability->freeWindowsForDay($r, $selectedDate, $indexedBusy);
                $availabilityMap[$r->getId()] = count($windows) > 0;
            }
        }

        return $this->render('resource_category/show.html.twig', [
            'resource_category' => $resourceCategory,
            'resources'         => $resources,
            'selectedDate'      => $selectedDate->format('Y-m-d'),
            'selectedDateEnd'   => $selectedDateEnd?->format('Y-m-d'),
            'selectedStart'     => $hasSlot ? $startTimeRaw : null,
            'selectedEnd'       => $hasSlot ? $endTimeRaw   : null,
            'availabilityMap'   => $availabilityMap,
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
