<?php

namespace App\Controller;

use App\Domain\Reservation\ReservationWorkflow;
use App\Dto\ReservationQuickDto;
use App\Entity\ReservationAttachment;
use App\Entity\ReservationAuditLog;
use App\Entity\ReservationInstance;
use App\Entity\ReservationResource;
use App\Entity\ReservationSeries;
use App\Entity\ReservationStatus;
use App\Entity\ReservationType;
use App\Entity\Resource;
use App\Entity\User;
use App\Form\ReservationQuickType;
use App\Repository\BlackoutInstanceRepository;
use App\Service\AvailabilityChecker;
use App\Service\ReferenceNumberGenerator;
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
    public function new(Request $request, EntityManagerInterface $em, AvailabilityChecker $availability, ReferenceNumberGenerator $refGen ,
                        BlackoutInstanceRepository $blackoutRepo,
                        SluggerInterface $slugger,
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

            // --- VALIDATION : interdire les réservations dans le passé ---
            // NOTE: on renvoie 422 (au lieu de 200) pour que Turbo Drive remplace bien
            // le DOM et affiche les erreurs. Sur 200 il ignore la réponse des POST.
            if ($dto->start && $dto->start < new \DateTime('today')) {
                return $this->render('reservation/new.html.twig', [
                    'form'       => $form,
                    'startError' => 'Impossible de réserver une date passée. Veuillez choisir une date à partir d\'aujourd\'hui.',
                ], new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY));
            }

            // --- VALIDATION : la fin doit être après le début ---
            if ($dto->start && $dto->end && $dto->end <= $dto->start) {
                return $this->render('reservation/new.html.twig', [
                    'form'     => $form,
                    'endError' => 'L\'heure de fin doit être postérieure à l\'heure de début.',
                ], new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY));
            }

            // Vérification fermeture (Blackout)
            if ($blackoutRepo->hasBlackout($dto->resource, $dto->start, $dto->end)) {
                return $this->render('reservation/new.html.twig', [
                    'form'      => $form,
                    'formError' => 'Impossible de réserver : la ressource est fermée (maintenance, travaux…) sur ce créneau.',
                ], new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY));
            }


            // --- VALIDATION : plage de validité du planning ---
            $schedule = $dto->resource->getSchedule();
            if ($schedule) {
                $scheduleStart = $schedule->getStartDate();
                $scheduleEnd   = $schedule->getEndDate();
                if ($scheduleStart && $dto->start < \DateTime::createFromImmutable($scheduleStart)) {
                    return $this->render('reservation/new.html.twig', [
                        'form'       => $form,
                        'startError' => sprintf(
                            'Les réservations ne sont pas encore ouvertes. Date d\'ouverture : %s.',
                            $scheduleStart->format('d/m/Y')
                        ),
                    ], new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY));
                }
                if ($scheduleEnd && $dto->end > \DateTime::createFromImmutable($scheduleEnd)->modify('+1 day')) {
                    return $this->render('reservation/new.html.twig', [
                        'form'     => $form,
                        'endError' => sprintf(
                            'Les réservations sont fermées après le %s.',
                            $scheduleEnd->format('d/m/Y')
                        ),
                    ], new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY));
                }
            }

            // 1 seule ressource
            $resource = $dto->resource;
            if (!$resource instanceof \App\Entity\Resource) {
                throw new \InvalidArgumentException('Ressource requise.');
            }

            // Pré-check rapide hors verrou : évite de prendre le lock pour rien
            if (!$availability->isFree($resource, $dto->start, $dto->end)) {
                return $this->render('reservation/new.html.twig', [
                    'form'      => $form,
                    'formError' => 'Ce créneau n\'est pas disponible : la ressource est déjà réservée ou hors des horaires d\'ouverture.',
                ], new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY));
            }

            // --- Création de la résa sous verrou concurrentiel ---
            // On sérialise par ressource via pg_advisory_xact_lock : tant que cette
            // transaction n'est pas COMMIT/ROLLBACK, aucune autre transaction ne peut
            // prendre le lock pour le même couple (namespace, resource_id).
            // Résultat : impossible de créer deux résa concurrentes sur la même
            // ressource, même en cas de burst simultané côté HTTP.
            //
            // Namespace arbitraire mais stable (évite les collisions avec d'autres
            // usages d'advisory locks dans l'appli).
            $lockNamespace = 0x52455356; // "RESV"

            try {
                $series = $em->wrapInTransaction(function () use (
                    $em, $dto, $resource, $availability, $form, $refGen,
                    $slugger, $attachmentsDir, $lockNamespace
                ) {
                    // Verrou Postgres : auto-libéré en fin de transaction.
                    $em->getConnection()->executeStatement(
                        'SELECT pg_advisory_xact_lock(:ns, :rid)',
                        ['ns' => $lockNamespace, 'rid' => (int) $resource->getId()]
                    );

                    // Re-check SOUS verrou : une autre requête a pu insérer entre
                    // le pré-check et l'acquisition du lock. C'est la fenêtre de
                    // race qu'on ferme ici.
                    if (!$availability->isFree($resource, $dto->start, $dto->end)) {
                        throw new \DomainException('concurrent_booking');
                    }

                    // Statut initial : En attente si approbation requise, Confirmée sinon (auto-approve)
                    $initialStatusId = $resource->isRequiresApproval()
                        ? ReservationStatus::PENDING
                        : ReservationStatus::APPROVED;

                    $series = (new ReservationSeries())
                        ->setTitle($dto->title)
                        ->setDescription($dto->description)
                        ->setOwner($this->getUser())
                        ->setType($em->getReference(ReservationType::class, ReservationType::STANDARD))
                        ->setStatus($em->getReference(ReservationStatus::class, $initialStatusId));
                    $em->persist($series);

                    /** @var UploadedFile[] $files */
                    $files = $form->get('attachments')->getData();
                    if ($files) {
                        // P0.6 — types réellement autorisés en pièce jointe.
                        $allowedMime = ['application/pdf', 'image/jpeg', 'image/png'];
                        foreach ($files as $file) {
                            if ($file) {
                                // Capture des métadonnées AVANT move() (après, le
                                // fichier temporaire n'existe plus → getMimeType casse).
                                // getMimeType() s'appuie sur finfo (contenu réel),
                                // donc résiste au spoofing de Content-Type / extension.
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
                    }

                    $link = (new ReservationResource())
                        ->setSeries($series)
                        ->setResource($resource)
                        ->setResourceLevelId(1);
                    $em->persist($link);

                    $instance = (new ReservationInstance())
                        ->setSeries($series)
                        ->setStartDate($dto->start)
                        ->setEndDate($dto->end)
                        ->setReferenceNumber($refGen->generate());
                    $em->persist($instance);

                    // Trace d'audit de création dans la même unit-of-work.
                    /** @var User|null $creator */
                    $creator = $this->getUser();
                    $em->persist(new ReservationAuditLog(
                        series: $series,
                        action: ReservationAuditLog::ACTION_CREATE,
                        actor: $creator,
                        fromStatusId: null,
                        toStatusId: $initialStatusId,
                    ));

                    $em->flush();

                    return $series;
                });
            } catch (\DomainException $e) {
                if ('concurrent_booking' === $e->getMessage()) {
                    return $this->render('reservation/new.html.twig', [
                        'form'      => $form,
                        'formError' => 'Ce créneau vient d\'être réservé par un autre utilisateur. Merci d\'en choisir un autre.',
                    ], new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY));
                }
                throw $e;
            }

            $this->addFlash('success', 'Votre réservation a bien été enregistrée.');

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
    ): Response {
        if ($series->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException("Vous ne pouvez pas annuler la réservation d'un autre.");
        }

        if (!$this->isCsrfTokenValid('cancel'.$series->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        try {
            /** @var User $actor */
            $actor = $this->getUser();
            $workflow->apply('cancel', $series, $actor);
            $this->addFlash('success', 'Votre réservation a bien été annulée.');
        } catch (\LogicException $e) {
            $this->addFlash('warning', $e->getMessage());
        }

        return $this->redirectToRoute('reservation_mine');
    }
}
