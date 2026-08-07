<?php

namespace App\Controller;

use App\Entity\Layout;
use App\Entity\Schedule;
use App\Form\ScheduleType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/schedule')]
final class ScheduleController extends AbstractController
{
    #[Route(name: 'app_schedule_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $schedules = $entityManager
            ->getRepository(Schedule::class)
            ->findAll();



        // Préparer les créneaux triés par planning (pour éviter sort côté Twig)
        $blocksBySchedule = [];
        foreach ($schedules as $schedule) {
            $blocks = [];
            if (null !== $schedule->getLayout()) {
                $blocks = $schedule->getLayout()->getTimeBlocks()->toArray();
                usort($blocks, static function ($a, $b) {
                    return $a->getStartTime() <=> $b->getStartTime();
                });
            }
            $blocksBySchedule[$schedule->getId()] = $blocks;
        }

        return $this->render('schedule/index.html.twig', [
            'schedules' => $schedules,
            'blocksBySchedule' => $blocksBySchedule,
        ]);
    }

    #[Route('/new', name: 'app_schedule_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $schedule = new Schedule();
        $layoutTz = 'Europe/Paris';


        $form = $this->createForm(ScheduleType::class, $schedule, [
            'layout_timezone' => $layoutTz, // ✅ préremplir le select fuseau
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $chosenTz = $form->has('timezone') ? ($form->get('timezone')->getData() ?? $layoutTz) : $layoutTz;
            $layout = (new Layout())
                ->setName($schedule->getName() ?: ('Layout '.$chosenTz))
                ->setTimezone($chosenTz)
                ->setLayoutType(Layout::TYPE_TIMES);

            $schedule->setLayout($layout);
            $entityManager->persist($layout);
            $entityManager->persist($schedule);
            $entityManager->flush();


            $this->addFlash('success', sprintf('Planning « %s » enregistré.', $schedule->getName()));

            $tab = $schedule->getLayout() ? 'grid' : 'params';

            return $this->redirectToRoute('app_schedule_edit', [
                'id'  => $schedule->getId(),
                'tab' => $tab,


            ], Response::HTTP_SEE_OTHER);

        }

        return $this->render('schedule/new.html.twig', [
            'schedule' => $schedule,
            'form' => $form,
            'title' => 'Nouveau planning',

        ]);
    }

    #[Route('/{id}', name: 'app_schedule_show', methods: ['GET'])]
    public function show(Schedule $schedule): Response
    {
        return $this->render('schedule/show.html.twig', [
            'schedule' => $schedule,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_schedule_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Schedule $schedule, EntityManagerInterface $entityManager): Response
    {
        $layoutTz = $schedule->getLayout()?->getTimezone() ?? 'Europe/Paris';
        $form = $this->createForm(ScheduleType::class, $schedule, [
            'layout_timezone' => $layoutTz, // ✅ préremplir le select fuseau
        ]);


        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $chosenTz = $form->get('timezone')->getData() ?? $layoutTz;

            $layout = $schedule->getLayout();
            if (!$layout) {
                $layout = (new Layout())
                    ->setName($schedule->getName() ?: ('Layout '.$chosenTz))
                    ->setTimezone($chosenTz)
                    ->setLayoutType(Layout::TYPE_TIMES);
                $schedule->setLayout($layout);
                $entityManager->persist($layout);
            } else {
                $layout->setTimezone($chosenTz);
            }
            $entityManager->flush();
            // Rester sur la page d’édition, et ouvrir le bon onglet
            $tab = $schedule->getLayout() ? 'grid' : 'params';

            return $this->redirectToRoute('app_schedule_edit', [
                'id' => $schedule->getId(),
                'tab' => $tab,

            ], Response::HTTP_SEE_OTHER);


//            return $this->redirectToRoute('app_schedule_index', [], Response::HTTP_SEE_OTHER);
        }
        $sortedBlocks = [];
        if ($layout = $schedule->getLayout()) {
            // On récupère les blocs sous forme de tableau simple
            $sortedBlocks = $layout->getTimeBlocks()->toArray();

            // On trie : D'abord par Jour (0=Dimanche...), puis par Heure de début
            usort($sortedBlocks, function ($a, $b) {
                $dayA = $a->getDayOfWeek() ?? 99; // 99 pour mettre "Tous les jours" à la fin (ou -1 pour au début)
                $dayB = $b->getDayOfWeek() ?? 99;

                if ($dayA !== $dayB) {
                    return $dayA <=> $dayB;
                }
                return $a->getStartTime() <=> $b->getStartTime();
            });
        }

        return $this->render('schedule/edit.html.twig', [
            'schedule' => $schedule,
            'form' => $form,
            'title' => 'Modifier planning',
            'sorted_blocks' => $sortedBlocks,
        ]);
    }

    #[Route('/{id}', name: 'app_schedule_delete', methods: ['POST'])]
    public function delete(Request $request, Schedule $schedule, EntityManagerInterface $entityManager): Response
    {
        if (!$this->container->has('security.csrf.token_manager') || $this->isCsrfTokenValid('delete'.$schedule->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($schedule);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_schedule_index', [], Response::HTTP_SEE_OTHER);
    }
}
