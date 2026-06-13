<?php

namespace App\Controller;

use App\Domain\Reservation\ReservationWorkflow;
use App\Dto\ReservationQuickDto;
use App\Entity\ReservationAttachment;
use App\Entity\ReservationSeries;
use App\Entity\Resource;
use App\Entity\User;
use App\Form\ReservationQuickType;
use App\Notification\ReservationNotifier;
use App\Security\Voter\ReservationSeriesVoter;
use App\Service\Exception\ConcurrentBookingException;
use App\Service\ReservationManager;
use App\Service\WaitlistService;
use App\Validator\ReservationRequestValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;


final class ReservationController extends AbstractController
{
    #[Route('/reservation', name: 'reservation_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request, EntityManagerInterface $em,
                        ReservationManager $reservationManager,
                        ReservationRequestValidator $validator,
                        SluggerInterface $slugger,
                        ReservationNotifier $notifier,
                        #[Autowire('%attachments_directory%')] string $attachmentsDir): Response
    {
        $dto = new ReservationQuickDto();

        $form = $this->createForm(ReservationQuickType::class, $dto);

        // --- A. PRÉ-REMPLISSAGE (GET) ---
        // Si on vient du planning, on remplit les champs du FORMULAIRE, pas le DTO
        if ($request->isMethod('GET')) {
            if ($d = $request->query->get('date')) {
                try {
                    $dateObj = new \DateTime($d);

                    // 1. On remplit les dates (Le jour)
                    $form->get('startDate')->setData($dateObj);
                    $form->get('endDate')->setData($dateObj);

                    // 2. Si aucune heure n'est fournie (cas de la Recherche), on met 09h-10h par défaut
                    // (Comme ça l'utilisateur n'a plus qu'à cliquer sur Valider)
                    if (!$request->query->get('start')) {
                        $defaultTimeStart = (new \DateTime())->setTime(9, 0); // 09:00
                        $defaultTimeEnd   = (new \DateTime())->setTime(10, 0); // 10:00

                        $form->get('startTime')->setData($defaultTimeStart);
                        $form->get('endTime')->setData($defaultTimeEnd);
                    }
                } catch (\Exception $e) {
                    // Si la date dans l'URL est invalide (ex: ?date=toto), on ignore silencieusement
                }
            }
            if ($s = $request->query->get('start')) {
                $st = new \DateTime($s);
                $form->get('startTime')->setData($st);
                // Fin par défaut = Début + 1h
                $form->get('endTime')->setData((clone $st)->modify('+1 hour'));
            }
            if ($rId = $request->query->get('resource')) {
                $res = $em->getRepository(Resource::class)->find($rId);

                if ($res) {
                    // On l'injecte dans le DTO pour que le champ soit pré-rempli
                    $dto->resource = $res;

                    // On force aussi la donnée dans le formulaire pour être sûr de l'affichage
                    $form->get('resource')->setData($res);
                }
            }
        }


        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // --- B. LA FUSION MAGIQUE ---
            // On récupère les données des champs "virtuels"

            $dStart = $form->get('startDate')->getData(); // <--- Changement ici
            $dEnd   = $form->get('endDate')->getData();
            $timeStart = $form->get('startTime')->getData();
            $timeEnd   = $form->get('endTime')->getData();

            if ($dStart && $timeStart && $dEnd && $timeEnd ) {
                // On reconstruit les DateTime complets pour ton DTO
                $start = (clone $dStart)->setTime((int)$timeStart->format('H'), (int)$timeStart->format('i'));
                $end   = (clone $dEnd)->setTime((int)$timeEnd->format('H'), (int)$timeEnd->format('i'));

                // On injecte ça dans le DTO pour que ton code existant fonctionne !
                $dto->start = $start;
                $dto->end   = $end;
            }
            // -----------------------------

            // --- Ressource (obligatoire) ---
            $resource = $dto->resource;
            if (!$resource instanceof \App\Entity\Resource) {
                throw new \InvalidArgumentException('Ressource requise.');
            }

            // --- Validation métier centralisée (passé, fin<début, blackout, planning, dispo) ---
            // En cas d'échec : 422 pour que Turbo Drive remplace le DOM et affiche l'erreur
            // (sur 200, Turbo ignore la réponse des POST).
            if ($error = $validator->validate($resource, $dto->start, $dto->end)) {
                $params = ['form' => $form, $error->field => $error->message];

                // Créneau indisponible → on propose l'inscription en liste d'attente.
                if ('unavailable' === $error->code) {
                    $params['waitlist'] = [
                        'resource' => $resource->getId(),
                        'start'    => $dto->start->format('c'),
                        'end'      => $dto->end->format('c'),
                    ];
                }

                return $this->render('reservation/new.html.twig', $params, new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY));
            }

            // --- Création sous verrou concurrentiel (transaction + audit) ---
            try {
                $series = $reservationManager->createWithLock(
                    $resource,
                    $this->getUser(),
                    $dto->start,
                    $dto->end,
                    $dto->title,
                    $dto->description,
                    // Pièces jointes : rattachées DANS la transaction de création.
                    function (ReservationSeries $series) use ($em, $form, $slugger, $attachmentsDir): void {
                        /** @var UploadedFile[] $files */
                        $files = $form->get('attachments')->getData();
                        if (!$files) {
                            return;
                        }

                        // P0.6 — types réellement autorisés en pièce jointe.
                        $allowedMime = ['application/pdf', 'image/jpeg', 'image/png'];
                        foreach ($files as $file) {
                            if ($file) {
                                // Capture des métadonnées AVANT move() (finfo sur le contenu
                                // réel → résiste au spoofing de Content-Type / extension).
                                $detectedMime = $file->getMimeType();
                                $clientName   = $file->getClientOriginalName();
                                $fileSize     = $file->getSize();

                                // P0.6 — rejet des polyglots / types non autorisés.
                                if (!in_array($detectedMime, $allowedMime, true)) {
                                    $this->addFlash('warning', 'Fichier rejeté (type non autorisé : ' . $detectedMime . ').');
                                    continue;
                                }

                                $originalFilename = pathinfo($clientName, PATHINFO_FILENAME);
                                $safeFilename = $slugger->slug($originalFilename);
                                $newFilename = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();

                                try {
                                    $file->move($attachmentsDir, $newFilename);

                                    $attachment = (new ReservationAttachment())
                                        ->setSeries($series)
                                        ->setFilename($newFilename)
                                        ->setOriginalName($clientName)
                                        ->setMimeType($detectedMime)
                                        ->setSize($fileSize);

                                    $em->persist($attachment);
                                } catch (FileException $e) {
                                    $this->addFlash('warning', 'Erreur lors de l\'envoi du fichier : ' . $e->getMessage());
                                }
                            } elseif ($file) {
                                $this->addFlash('warning', 'Fichier ignoré (Erreur upload : ' . $file->getError() . ')');
                            }
                        }
                    },
                );
            } catch (ConcurrentBookingException $e) {
                return $this->render('reservation/new.html.twig', [
                    'form'      => $form,
                    'formError' => 'Ce créneau vient d\'être réservé par un autre utilisateur. Merci d\'en choisir un autre.',
                ], new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY));
            }

            $this->addFlash('success', 'Votre réservation a bien été enregistrée.');

            // Notification (non bloquante) au demandeur.
            $notifier->created($series);

            return $this->redirectToRoute('reservation_show', ['uuid' => $series->getUuid()]);
        }

        // Si le form a été soumis mais invalide (contraintes Symfony par ex.), on renvoie 422
        // pour que Turbo affiche bien les erreurs côté client.
        $status = ($form->isSubmitted() && !$form->isValid())
            ? Response::HTTP_UNPROCESSABLE_ENTITY
            : Response::HTTP_OK;

        return $this->render('reservation/new.html.twig',
            ['form' => $form], new Response(null, $status));


    }


    #[Route('', name: 'reservation_index', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(EntityManagerInterface $em): Response
    {
        $series = $em->getRepository(ReservationSeries::class)->findBy([], ['dateCreated' => 'DESC'], 50);
        return $this->render('reservation/index.html.twig', ['series' => $series]);
    }
    #[Route('/mine', name: 'reservation_mine', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function mine(EntityManagerInterface $em): Response
    {
        $series = $em->getRepository(ReservationSeries::class)->findBy(
            ['owner' => $this->getUser()],
            ['dateCreated' => 'DESC']
        );
        return $this->render('reservation/mine.html.twig', ['series' => $series]);
    }

    // Page très simple pour voir la série créée (à adapter)
    #[Route('/reservation/{uuid}', name: 'reservation_show', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function show (#[MapEntity(mapping: ['uuid' => 'uuid'])] ReservationSeries $series): Response
    {
        return $this->render('reservation/show.html.twig', [
            'series' => $series,
        ]);
    }

    #[Route('/reservation/{uuid}/cancel', name: 'reservation_cancel', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function cancel(
        Request $request,
        #[MapEntity(mapping: ['uuid' => 'uuid'])] ReservationSeries $series,
        ReservationWorkflow $workflow,
        ReservationNotifier $notifier,
        WaitlistService $waitlist,
    ): Response {
        $this->denyAccessUnlessGranted(ReservationSeriesVoter::CANCEL, $series);

        if (!$this->isCsrfTokenValid('cancel'.$series->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        try {
            /** @var User $actor */
            $actor = $this->getUser();
            $workflow->apply('cancel', $series, $actor);
            $this->addFlash('success', 'Votre réservation a bien été annulée.');

            // Notification (non bloquante) au demandeur.
            $notifier->cancelled($series);

            // Liste d'attente : prévenir les personnes en attente sur ce créneau.
            $waitlist->notifyForFreedSeries($series);
        } catch (\LogicException $e) {
            $this->addFlash('warning', $e->getMessage());
        }

        return $this->redirectToRoute('reservation_mine');
    }

    #[Route('/reservation/{uuid}/checkin', name: 'reservation_checkin', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function checkin(
        Request $request,
        #[MapEntity(mapping: ['uuid' => 'uuid'])] ReservationSeries $series,
        EntityManagerInterface $em,
    ): Response {
        $this->denyAccessUnlessGranted(ReservationSeriesVoter::CANCEL, $series);

        if (!$this->isCsrfTokenValid('checkin'.$series->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $now  = new \DateTime();
        $done = false;
        foreach ($series->getInstances() as $instance) {
            if (null === $instance->getCheckinDate()) {
                $instance->setCheckinDate($now);
                $done = true;
            }
        }

        if ($done) {
            $em->flush();
            $this->addFlash('success', 'Arrivée confirmée : votre réservation ne sera pas libérée automatiquement.');
        } else {
            $this->addFlash('info', 'Le check-in a déjà été effectué.');
        }

        return $this->redirectToRoute('reservation_show', ['uuid' => $series->getUuid()]);
    }
}
