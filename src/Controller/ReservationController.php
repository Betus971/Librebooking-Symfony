<?php

namespace App\Controller;

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
use App\Security\Voter\ReservationSeriesVoter;
use App\Service\AvailabilityChecker;
use App\Service\ReferenceNumberGenerator;
use App\Service\ReservationManager;
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
                        ReservationManager $reservationManager,
                        \App\Repository\AccessoireRepository $accessoireRepo,
                        #[Autowire('%attachments_directory%')] string $attachmentsDir): Response
    {
        $dto = new ReservationQuickDto();

        $form = $this->createForm(ReservationQuickType::class, $dto);

        // --- Accessoires (matériel mobile) ---
        // Catalogue actif proposé dans la section « Accessoires » du formulaire,
        // et quantités saisies (name="accessoires[<id>]") relues telles quelles
        // pour ré-afficher les valeurs en cas d'erreur (redisplay 422).
        $activeAccessoires   = $accessoireRepo->findActiveOrdered();
        $submittedAccessoires = $request->isMethod('POST') ? $request->request->all('accessoires') : [];

        // Helper de rendu : garantit que 'accessoires' et 'submittedAccessoires'
        // sont TOUJOURS passés au template, quel que soit le point de sortie.
        $renderNew = function (array $vars = [], int $status = Response::HTTP_OK) use (&$form, $activeAccessoires, &$submittedAccessoires): Response {
            return $this->render('reservation/new.html.twig', array_merge([
                'form'                 => $form,
                'accessoires'          => $activeAccessoires,
                'submittedAccessoires' => $submittedAccessoires,
            ], $vars), new Response(null, $status));
        };

        // --- A. PRÉ-REMPLISSAGE (GET) ---
        // Si on vient du planning, on remplit les champs du FORMULAIRE, pas le DTO
        if ($request->isMethod('GET')) {
            if ($d = $request->query->get('date')) {
                try {
                    $dateObj = new \DateTime($d);

                    // 1. On remplit les dates (Le jour)
                    $form->get('startDate')->setData($dateObj);
                    // endDate par défaut = même jour. Si ?date_end fourni
                    // (provient de la page « Salles dispo » avec une période),
                    // on l'utilise pour préremplir une vraie résa multi-jours.
                    $endDateObj = $dateObj;
                    if ($de = $request->query->get('date_end')) {
                        try {
                            $endDateObj = new \DateTime($de);
                        } catch (\Exception) {
                            // Format invalide : on garde la date de début.
                        }
                    }
                    $form->get('endDate')->setData($endDateObj);

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
                // Fin par défaut = Début + 1h, écrasée plus bas si ?end= fourni.
                $form->get('endTime')->setData((clone $st)->modify('+1 hour'));
            }
            // Paramètre ?end=HH:MM (utilisé quand on vient de la page "Salles
            // disponibles" avec un créneau saisi par l'utilisateur). Préremplit
            // l'heure de fin EXACTE plutôt que start + 1h.
            if ($e = $request->query->get('end')) {
                try {
                    $form->get('endTime')->setData(new \DateTime($e));
                } catch (\Exception) {
                    // Format invalide : on garde le défaut "+1h" calculé au-dessus.
                }
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
                return $renderNew([
                    'startError' => 'Impossible de réserver une date passée. Veuillez choisir une date à partir d\'aujourd\'hui.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // --- VALIDATION : la fin doit être après le début ---
            if ($dto->start && $dto->end && $dto->end <= $dto->start) {
                return $renderNew([
                    'endError' => 'L\'heure de fin doit être postérieure à l\'heure de début.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Vérification fermeture (Blackout)
            if ($blackoutRepo->hasBlackout($dto->resource, $dto->start, $dto->end)) {
                return $renderNew([
                    'formError' => 'Impossible de réserver : la ressource est fermée (maintenance, travaux…) sur ce créneau.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }


            // --- VALIDATION : plage de validité du planning ---
            $schedule = $dto->resource->getSchedule();
            if ($schedule) {
                $scheduleStart = $schedule->getStartDate();
                $scheduleEnd   = $schedule->getEndDate();
                if ($scheduleStart && $dto->start < \DateTime::createFromImmutable($scheduleStart)) {
                    return $renderNew([
                        'startError' => sprintf(
                            'Les réservations ne sont pas encore ouvertes. Date d\'ouverture : %s.',
                            $scheduleStart->format('d/m/Y')
                        ),
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
                if ($scheduleEnd && $dto->end > \DateTime::createFromImmutable($scheduleEnd)->modify('+1 day')) {
                    return $renderNew([
                        'endError' => sprintf(
                            'Les réservations sont fermées après le %s.',
                            $scheduleEnd->format('d/m/Y')
                        ),
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
            }

            // 1 seule ressource
            $resource = $dto->resource;
            if (!$resource instanceof \App\Entity\Resource) {
                throw new \InvalidArgumentException('Ressource requise.');
            }

            // Pré-check rapide hors verrou : évite de prendre le lock pour rien
            if (!$availability->isFree($resource, $dto->start, $dto->end)) {
                return $renderNew([
                    'formError' => 'Ce créneau n\'est pas disponible : la ressource est déjà réservée ou hors des horaires d\'ouverture.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Accessoires demandés → DTO (relus depuis name="accessoires[<id>]").
            $dto->accessoires = is_array($submittedAccessoires) ? $submittedAccessoires : [];

            // --- Création de la résa sous verrou concurrentiel (RF-1) ---
            // La logique de transaction / verrou Postgres / persistance vit
            // désormais dans ReservationManager::createWithLock(). Le contrôleur
            // ne conserve que la gestion HTTP des pièces jointes (upload + flash),
            // passée en callback exécuté À L'INTÉRIEUR de la transaction, à la
            // même position qu'avant (juste après le persist de la série).
            $persistAttachments = function (ReservationSeries $series) use ($form, $em, $slugger, $attachmentsDir): void {
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
            };

            try {
                $series = $reservationManager->createWithLock(
                    $resource,
                    $this->getUser(),
                    $dto,
                    $persistAttachments
                );
            } catch (\DomainException $e) {
                if ('concurrent_booking' === $e->getMessage()) {
                    return $renderNew([
                        'formError' => 'Ce créneau vient d\'être réservé par un autre utilisateur. Merci d\'en choisir un autre.',
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
                // Stock d'accessoire dépassé : message 'accessoire_stock:<nom>:<dispo>'.
                if (str_starts_with($e->getMessage(), 'accessoire_stock:')) {
                    [, $nom, $dispo] = explode(':', $e->getMessage(), 3);
                    return $renderNew([
                        'formError' => sprintf(
                            'Quantité demandée trop élevée pour l\'accessoire « %s » : %d disponible%s au total.',
                            $nom, (int) $dispo, ((int) $dispo) > 1 ? 's' : ''
                        ),
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
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

        return $renderNew([], $status);
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
        // Le calendrier partagé est cliquable par tous → tout utilisateur peut
        // ouvrir la fiche. Mais seuls le propriétaire et les gestionnaires
        // (VIEW_DETAILS) voient les données sensibles (e-mail du demandeur,
        // description, pièces jointes, actions). Les autres n'ont que
        // l'essentiel (ressource, créneau, statut, qui a réservé).
        return $this->render('reservation/show.html.twig', [
            'series' => $series,
            'canViewDetails' => $this->isGranted(ReservationSeriesVoter::VIEW_DETAILS, $series),
        ]);
    }

                #[Route('/reservation/{uuid}/delete', name: 'reservation_delete', methods: ['POST'])]
                public function delete(Request $request, #[MapEntity(mapping: ['uuid' => 'uuid'])] ?ReservationSeries $series, EntityManagerInterface $em): Response
                {
                    // Tolérance : réservation déjà supprimée (double-clic, page restée
                    // ouverte…) → on ne renvoie pas une page d'erreur, juste un message.
                    if (null === $series) {
                        $this->addFlash('info', 'Cette réservation a déjà été supprimée.');

                        return $this->redirectToRoute('reservation_mine');
                    }

                    $this->denyAccessUnlessGranted(ReservationSeriesVoter::MANAGE, $series);

                    if (!$this->container->has('security.csrf.token_manager') || $this->isCsrfTokenValid('delete'.$series->getId(), $request->request->get('_token'))) {
                        $em->remove($series);
                        $em->flush();
                        $this->addFlash('success', 'La réservation a été supprimée définitivement.');
                    }

                    return $this->redirectToRoute('reservation_mine');
                }

                #[Route('/reservation/{uuid}/cancel', name: 'reservation_cancel', methods: ['POST'])]

                public function cancel(Request $request,#[MapEntity(mapping: ['uuid' => 'uuid'])] ReservationSeries $series, EntityManagerInterface $em): Response
                {
                    // 1. Sécurité (RF-14) : annulation réservée au propriétaire,
                    //    via le Voter (CANCEL_OWN) plutôt qu'un contrôle inline.
                    $this->denyAccessUnlessGranted(ReservationSeriesVoter::CANCEL_OWN, $series);

                    // 2. Sécurité CSRF (Protection contre les failles)
                    if (!$this->container->has('security.csrf.token_manager') || $this->isCsrfTokenValid('cancel'.$series->getId(), $request->request->get('_token'))) {

                        $series->setStatus($em->getReference(ReservationStatus::class, ReservationStatus::CANCELLED));

                        $em->flush();
                        $this->addFlash('success', 'Votre réservation a bien été annulée.');
                    }

                    return $this->redirectToRoute('reservation_mine');
                }
}
