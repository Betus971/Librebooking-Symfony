<?php

namespace App\Controller;

use App\Entity\Layout;
use App\Entity\Resource;
use App\Entity\Schedule;
use App\Entity\TimeBlock;
use App\Form\TimeBlockType;
use App\Repository\TimeBlockRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/time/block')]
final class TimeBlockController extends AbstractController
{
    /**
     * Réactive (crée) un créneau ouvert pour un jour + une plage donnés, depuis
     * un clic sur une case blanche de la grille « semaine type ». Symétrique de
     * la fermeture (édition/suppression d'un créneau vert) → toggle intuitif.
     */
    #[Route('/schedules/{id}/activate', name: 'app_time_block_activate', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN_RESSOURCE')]
    public function activate(Schedule $schedule, Request $req, EntityManagerInterface $em, TimeBlockRepository $repo): Response
    {
        $layout = $schedule->getLayout();
        $redirect = fn () => $this->redirectToRoute('app_schedule_edit', ['id' => $schedule->getId(), 'tab' => 'grid']);

        if (!$layout) {
            $this->addFlash('warning', 'Ce planning n\'a pas de mise en page (layout) associée.');
            return $redirect();
        }

        if ($this->container->has('security.csrf.token_manager')
            && !$this->isCsrfTokenValid('activate_slot', (string) $req->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');
            return $redirect();
        }

        $dow      = (int) $req->request->get('dow');
        $startStr = (string) $req->request->get('start');
        $endStr   = (string) $req->request->get('end');

        $tz = new \DateTimeZone($layout->getTimezone());
        $s  = \DateTimeImmutable::createFromFormat('H:i', $startStr, $tz) ?: null;
        $e  = \DateTimeImmutable::createFromFormat('H:i', $endStr, $tz) ?: null;

        if (!$s || !$e || $e <= $s || $dow < 0 || $dow > 6) {
            $this->addFlash('warning', 'Créneau invalide.');
            return $redirect();
        }

        // Anti-doublon : ne recrée pas un créneau ouvert identique.
        $exists = (int) $repo->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.layout = :l')
            ->andWhere('t.availabilityCode = :open')
            ->andWhere('t.dayOfWeek = :d')
            ->andWhere('t.startTime = :s')
            ->andWhere('t.endTime = :e')
            ->setParameter('l', $layout)
            ->setParameter('open', TimeBlock::OPEN)
            ->setParameter('d', $dow)
            ->setParameter('s', $s)
            ->setParameter('e', $e)
            ->getQuery()->getSingleScalarResult();

        if ($exists === 0) {
            $tb = (new TimeBlock())
                ->setLayout($layout)
                ->setDayOfWeek($dow)
                ->setStartTime($s)
                ->setEndTime($e)
                ->setAvailabilityCode(TimeBlock::OPEN);
            $em->persist($tb);
            $em->flush();
            $this->addFlash('success', 'Créneau réactivé.');
        } else {
            $this->addFlash('info', 'Ce créneau est déjà actif.');
        }

        return $redirect();
    }

//    #[Route(name: 'app_time_block_index', methods: ['GET'])]
//    public function index(TimeBlockRepository $timeBlockRepository): Response
//    {
//        return $this->render('time_block/index.html.twig', [
//            'time_blocks' => $timeBlockRepository->findAll(),
//        ]);
//    }




    #[Route('/schedules/{id}/new', name: 'app_schedule_time_block_new', methods: ['GET','POST'])]
    public function newForSchedule(Schedule $schedule, Request $req, EntityManagerInterface $em,  TimeBlockRepository $timeBlockRepository): Response
    {
        $layout = $schedule->getLayout();
        if (!$layout) {
            $this->addFlash('warning', 'Erreur critique : Ce planning n\'a pas de Layout associé.');
            return $this->redirectToRoute('app_schedule_edit', ['id' => $schedule->getId(), 'tab' => 'params']);
        }

        $block = (new TimeBlock())->setLayout($layout);

        $form = $this->createForm(TimeBlockType::class, $block, [
            'layout_timezone' => $layout->getTimezone(),
        ]);
        $form->handleRequest($req);

        // Helper pour rediriger vers l'onglet "Grille"
        $redirect = fn() => $this->redirectToRoute('app_schedule_edit', [
            'id'  => $schedule->getId(),
            'tab' => 'grid',
        ]);

        if ($form->isSubmitted() && $form->isValid()) {

            // 1) Action : VIDER
            if ($form->get('clear')->isClicked()) {
                $checkedDays = (array) ($form->get('days')->getData() ?? []);
                $deleted = empty($checkedDays)
                    ? $timeBlockRepository->deleteAllWeekForLayout($layout)
                    : $timeBlockRepository->deleteByLayoutAndDays($layout, $checkedDays);

                $this->addFlash('success', sprintf('%d créneau(x) supprimé(s).', $deleted));
                return $redirect();
            }

            // 2) Action : GÉNÉRER (batch, utilise les cases à cocher "days")
            if ($form->get('generate')->isClicked()) {
                $days = (array) ($form->get('days')->getData() ?? []);
                if (empty($days)) {
                    // Aucune case cochée = on génère sur toute la semaine
                    $days = [0, 1, 2, 3, 4, 5, 6];
                }

                $startTime = $form->get('startTime')->getData();
                $endTime   = $form->get('endTime')->getData();
                $slot      = (int) $form->get('slotDuration')->getData();
                $avail     = (int) $form->get('availabilityCode')->getData();
                $label     = $form->get('label')->getData();
                $endLabel  = $form->get('endLabel')->getData();

                if (!$startTime || !$endTime || $endTime <= $startTime) {
                    $this->addFlash('warning', 'Plage horaire invalide (Fin avant Début).');
                    return $redirect();
                }
                if ($slot <= 0) {
                    $this->addFlash('warning', 'La durée du créneau doit être positive.');
                    return $redirect();
                }

                // Suppression préalable via le repository (évite les doublons)
                $timeBlockRepository->deleteByLayoutAndDays($layout, $days);

                // Boucle de génération
                $interval = new \DateInterval('PT'.$slot.'M');
                foreach ($days as $dow) {
                    for ($cur = clone $startTime; $cur < $endTime; $cur = $next) {
                        $next = (clone $cur)->add($interval);
                        if ($next > $endTime) { $next = clone $endTime; }

                        $tb = (new TimeBlock())
                            ->setLayout($layout)
                            ->setDayOfWeek((int)$dow)
                            ->setStartTime($cur)
                            ->setEndTime($next)
                            ->setAvailabilityCode($avail)
                            ->setLabel($label)
                            ->setEndLabel($endLabel);

                        $em->persist($tb);
                    }
                }

                $em->flush();
                $this->addFlash('success', 'Grille générée avec succès.');
                return $redirect();
            }

            // 3) Action : ENREGISTRER (unitaire — un seul créneau sur un jour précis)
            if ($form->get('save')->isClicked()) {
                // startTime et endTime sont mapped=false : on les lit manuellement
                $startTime = $form->get('startTime')->getData();
                $endTime   = $form->get('endTime')->getData();

                if (!$startTime || !$endTime || $endTime <= $startTime) {
                    $this->addFlash('warning', 'Plage horaire invalide (Fin avant Début).');
                    return $redirect();
                }

                $block->setStartTime($startTime)->setEndTime($endTime);
                $em->persist($block);
                $em->flush();
                $this->addFlash('success', 'Créneau ajouté manuellement.');
                return $redirect();
            }
        }

        return $this->render('time_block/new.html.twig', [
            'form'     => $form,
            'layout'   => $layout,
            'schedule' => $schedule,
        ]);
    }


    #[Route('/{id}', name: 'app_time_block_show', methods: ['GET'])]
    public function show(TimeBlock $timeBlock): Response
    {
        return $this->render('time_block/show.html.twig', [
            'time_block' => $timeBlock,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_time_block_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, TimeBlock $timeBlock, EntityManagerInterface $entityManager): Response
    {
        $layout   = $timeBlock->getLayout();
        // On essaie de retrouver le Schedule parent pour rediriger au bon endroit
        $schedule = $layout->getSchedules()->first() ?: null;

        $form = $this->createForm(TimeBlockType::class, $timeBlock, [
            'layout_timezone' => $layout->getTimezone(),
        ]);

        // startTime / endTime sont mapped=false → ils ne se préremplissent pas
        // tout seuls en édition. On pousse la valeur de l'entité dans les champs
        // AVANT handleRequest (sur POST, handleRequest écrasera avec la saisie).
        //
        // La valeur rechargée par Doctrine est en UTC, or le champ TimeType est
        // configuré en model_timezone du layout (ex. Europe/Paris). On reconstruit
        // donc l'heure (même valeur d'horloge H:i) dans le bon fuseau pour éviter
        // à la fois l'erreur de fuseau et tout décalage d'affichage.
        $tz = new \DateTimeZone($layout->getTimezone());
        if ($timeBlock->getStartTime()) {
            $form->get('startTime')->setData(\DateTimeImmutable::createFromFormat('H:i', $timeBlock->getStartTime()->format('H:i'), $tz));
        }
        if ($timeBlock->getEndTime()) {
            $form->get('endTime')->setData(\DateTimeImmutable::createFromFormat('H:i', $timeBlock->getEndTime()->format('H:i'), $tz));
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Champs non mappés : on les réapplique à l'entité avant flush,
            // sinon la modification des heures serait ignorée.
            $startTime = $form->get('startTime')->getData();
            $endTime   = $form->get('endTime')->getData();
            if (!$startTime || !$endTime || $endTime <= $startTime) {
                $this->addFlash('danger', 'Renseignez une heure de début et de fin valides (la fin doit être après le début).');

                return $this->redirectToRoute('app_time_block_edit', ['id' => $timeBlock->getId()]);
            }
            $timeBlock->setStartTime($startTime)->setEndTime($endTime);

            $entityManager->flush();
            $this->addFlash('success', 'Créneau mis à jour.');

            // Retour intelligent : vers le planning si on sait lequel, sinon vers le layout
            return $schedule
                ? $this->redirectToRoute('app_schedule_edit', ['id' => $schedule->getId(), 'tab' => 'grid'])
                : $this->redirectToRoute('app_layout_edit',   ['id' => $layout->getId()]);
        }

        return $this->render('time_block/edit.html.twig', [
            'time_block' => $timeBlock,
            'form' => $form,
            'title' => 'Modifier le créneau',
            'layout' => $layout,
        ]);
    }

    // Suppression via un formulaire (POST) pour la sécurité
    #[Route('/{id}/delete', name: 'app_time_block_delete', methods: ['POST'])]
    public function delete(TimeBlock $timeBlock, Request $req, EntityManagerInterface $em): Response
    {
        $layout = $timeBlock->getLayout();
        $schedule = $layout->getSchedules()->first() ?: null;

        // CORRECTION CSRF : On utilise un nom cohérent 'delete_timeblock_ID'
        if (!$this->container->has('security.csrf.token_manager') || $this->isCsrfTokenValid('delete_timeblock_'.$timeBlock->getId(), $req->request->get('_token'))) {
            $em->remove($timeBlock);
            $em->flush();
            $this->addFlash('success', 'Créneau supprimé.');
        } else {
            $this->addFlash('danger', 'Token de sécurité invalide.');
        }

        return $schedule
            ? $this->redirectToRoute('app_schedule_edit', ['id' => $schedule->getId(), 'tab' => 'grid'])
            : $this->redirectToRoute('app_layout_edit',   ['id' => $layout->getId()]);
    }



}
