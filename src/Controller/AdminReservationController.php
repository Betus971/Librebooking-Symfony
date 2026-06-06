<?php

namespace App\Controller;

use App\Domain\Reservation\ReservationWorkflow;
use App\Entity\ReservationAttachment;
use App\Entity\ReservationSeries;
use App\Entity\User;
use App\Repository\ReservationSeriesRepository;
use App\Repository\ResourceRepository;
use App\Security\Voter\ReservationSeriesVoter;
use Knp\Bundle\PaginatorBundle\Pagination\SlidingPagination;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/reservation', name: 'admin_reservation_')]
// Ouvert aux gestionnaires ressource. ROLE_SUPER_ADMIN hérite, donc il garde tout.
// Le scope "ne voit/valide que SES ressources" est géré ci-dessous :
//   - listing : injection de $user->resourceGroupIds dans les filtres du repo
//   - actions (approve/reject/cancel) : voter ReservationSeriesVoter::MANAGE
#[IsGranted('ROLE_ADMIN_RESSOURCE')]
final class AdminReservationController extends AbstractController
{
    /**
     * Injecte le SCOPE DE VISIBILITÉ HYBRIDE (P3.4) dans le tableau de filtres
     * passé au repository.
     *
     * - Super-admin : aucune restriction (on ne pose pas la clé 'scoped').
     * - Gestionnaire (ROLE_ADMIN_RESSOURCE) : on restreint aux séries dont au
     *   moins une ressource appartient à ses ResourceGroup OU porte son code
     *   unité (cf. ReservationSeriesRepository::applyHybridScope).
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function applyScope(array $filters): array
    {
        if ($this->isGranted('ROLE_SUPER_ADMIN')) {
            return $filters; // pas de scope
        }

        $filters['scoped'] = true;

        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            // Pas de user exploitable : scope vide → ne voit rien.
            $filters['resourceGroupIds'] = [];
            $filters['scopeCodeUnite']   = null;
            return $filters;
        }

        $ids = [];
        foreach ($user->getResourceGroups() as $g) {
            if (null !== $g->getId()) {
                $ids[] = $g->getId();
            }
        }
        $filters['resourceGroupIds'] = $ids;                 // couche manuelle
        $filters['scopeCodeUnite']   = $user->getCodeunite(); // couche SSO

        return $filters;
    }
    // --- VUE DÉTAIL (avec pièces jointes) ---
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(ReservationSeries $series): Response
    {
        // Un admin ressource ne voit le détail que s'il gère au moins une ressource de la série.
        $this->denyAccessUnlessGranted(ReservationSeriesVoter::VIEW_DETAILS, $series);

        return $this->render('admin_reservation/show.html.twig', [
            'series' => $series,
        ]);
    }

    // --- TÉLÉCHARGEMENT PIÈCE JOINTE ---
    #[Route('/attachment/{id}/download', name: 'attachment_download', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function downloadAttachment(
        ReservationAttachment $attachment,
        #[Autowire('%attachments_directory%')] string $attachmentsDir
    ): Response {
        // Le droit de télécharger suit le droit de voir la série (scope groupe).
        $series = $attachment->getSeries();
        if (null !== $series) {
            $this->denyAccessUnlessGranted(ReservationSeriesVoter::VIEW_DETAILS, $series);
        }

        // P1.1 — Anti path traversal : on tronque tout composant de chemin
        // (basename) puis on vérifie que le chemin résolu reste bien dans le
        // dossier autorisé. Empêche `../../etc/passwd` même si le filename en
        // base était corrompu.
        $safeName = basename((string) $attachment->getFilename());
        $filepath = $attachmentsDir . DIRECTORY_SEPARATOR . $safeName;

        $realDir  = realpath($attachmentsDir);
        $realFile = realpath($filepath);
        if ($realDir === false || $realFile === false
            || !str_starts_with($realFile, $realDir . DIRECTORY_SEPARATOR)) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        return $this->file(
            $realFile,
            $attachment->getOriginalName(),
            ResponseHeaderBag::DISPOSITION_ATTACHMENT
        );
    }

    /**
     * Construit une SlidingPagination KnpPaginator à partir d'un tuple
     * `[rows, total]` déjà calculé par le repository. Permet de partager le
     * même rendu DSFR (`templates/_partials/_pagination.html.twig`) avec les
     * autres listes du projet, sans réécrire la logique de count du repo
     * (qui contient des GROUP BY non triviaux et un scope par groupe).
     *
     * @param array<int, mixed> $rows
     */
    private function buildPagination(
        array $rows,
        int $total,
        int $page,
        int $perPage,
        string $route,
        array $params = []
    ): SlidingPagination {
        $pagination = new SlidingPagination([]);
        $pagination->setItems($rows);
        $pagination->setCurrentPageNumber($page);
        $pagination->setItemNumberPerPage($perPage);
        $pagination->setTotalItemCount($total);
        $pagination->setUsedRoute($route);
        // ⚠️  Quand on construit la pagination MANUELLEMENT (sans passer par
        // `$paginator->paginate()`), il faut initialiser ces tableaux à []
        // sinon `SlidingPagination::getPaginationData()` fait
        // `array_merge($viewData, null, null)` → TypeError.
        $pagination->setPaginatorOptions([]);
        $pagination->setCustomParameters([]);
        // ⚠️  Le template par défaut configuré dans knp_paginator.yaml n'est
        // appliqué qu'au passage par le service `$paginator->paginate()`.
        // En construction manuelle, on le pose explicitement ici.
        $pagination->setTemplate('_partials/_pagination.html.twig');
        // Conserve les filtres dans les liens de pagination.
        foreach ($params as $k => $v) {
            $pagination->setParam($k, $v);
        }
        return $pagination;
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        ReservationSeriesRepository $repo,
        Request $request,
        #[Autowire(param: 'app.pagination.per_page')] int $perPage
    ): Response {
        $page   = max(1, $request->query->getInt('page', 1));
        $offset = ($page - 1) * $perPage;
        $q = trim((string)$request->query->get('q', ''));

        $filters = ['q' => $q];

        // Scope de visibilité hybride (groupes OU code unité). Super-admin : aucun filtre.
        $filters = $this->applyScope($filters);

        // Appel au repository (méthode globale)
        [$rows, $total] = $repo->findAllWithFilters($filters, $perPage, $offset);

        $pagination = $this->buildPagination(
            $rows, $total, $page, $perPage,
            'admin_reservation_index',
            ['q' => $q]
        );

        return $this->render('admin_reservation/index.html.twig', [
            'rows'       => $rows,
            'total'      => $total,
            'pagination' => $pagination,
            'q'          => $q,
        ]);
    }

    #[Route('/pending', name: 'pending', methods: ['GET'])]
    public function pending(
        ReservationSeriesRepository $repo,
        Request $request,
        ResourceRepository $resourceRepo,
        #[Autowire(param: 'app.pagination.per_page')] int $perPage
    ): Response {
        $filters = [
            'q'        => trim((string) $request->query->get('q', '')),
            'resource' => $request->query->getInt('resource', 0) ?: null,
            'from'     => $request->query->get('from') ? new \DateTimeImmutable($request->query->get('from')) : null,
            'to'       => $request->query->get('to') ? new \DateTimeImmutable($request->query->get('to')) : null,
            'approval' => $request->query->get('approval') ?: 'all', // all|req|noreq
        ];

        // Scope de visibilité hybride (groupes OU code unité). Super-admin : aucun filtre.
        $filters = $this->applyScope($filters);

        $page   = max(1, $request->query->getInt('page', 1));
        // Per-page paramétrable ici (l'utilisateur peut ajuster via ?limit=…)
        // mais borné pour éviter abus.
        $limit = min(100, max(10, $request->query->getInt('limit', $perPage)));
        $offset = ($page - 1) * $limit;

        [$rows, $total] = $repo->findPendingWithFilters($filters, $limit, $offset);

        // pour le <select> Ressource
        $resources = $resourceRepo->findAllForSelect();

        // Paramètres à conserver dans les liens de pagination (filtres actifs).
        $queryParams = [
            'q'        => $filters['q'],
            'resource' => $filters['resource'],
            'from'     => $request->query->get('from'),
            'to'       => $request->query->get('to'),
            'approval' => $filters['approval'],
            'limit'    => $limit,
        ];
        // On vire les vides pour ne pas polluer les URLs.
        $queryParams = array_filter($queryParams, static fn($v) => $v !== null && $v !== '');

        $pagination = $this->buildPagination(
            $rows, $total, $page, $limit,
            'admin_reservation_pending',
            $queryParams
        );

        return $this->render('admin_reservation/pending.html.twig', [
            'rows'       => $rows,
            'total'      => $total,
            'limit'      => $limit,
            'filters'    => $filters,
            'resources'  => $resources,
            'pagination' => $pagination,
        ]);
    }

    // APPROUVER

    #[Route('/{id}/approve', name: 'approve', methods: ['POST'])]
    public function approve(ReservationSeries $series, Request $request, ReservationWorkflow $wf, MailerInterface $mailer): Response
    {
        // Un admin ressource ne peut approuver que sur SES ressources.
        $this->denyAccessUnlessGranted(ReservationSeriesVoter::MANAGE, $series);

        if ($this->container->has('security.csrf.token_manager') && !$this->isCsrfTokenValid('approve'.$series->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('CSRF invalid');
        }
        try {
            $wf->ensureAllowed('approve', $series);
            /** @var User|null $actor */
            $actor = $this->getUser();
            $wf->apply('approve', $series, $actor);
            $this->addFlash('success', 'Réservation approuvée.');
        } catch (\DomainException $e) {
            $this->addFlash('warning', $e->getMessage());
            return $this->redirectToRoute('admin_reservation_pending');
        }

        // --- ENVOI DE L'EMAIL (séparé du workflow pour ne pas bloquer en cas d'erreur SMTP) ---
        if ($series->getOwner() && $series->getOwner()->getEmail()) {
            try {
                $email = (new TemplatedEmail())
                    ->from(new Address('ne-pas-repondre@ton-domaine.fr', 'Service Logistique'))
                    ->to($series->getOwner()->getEmail())
                    ->subject('✅ Réservation approuvée : ' . $series->getTitle())
                    ->htmlTemplate('emails/reservation_status.html.twig')
                    ->context([
                        'demandeur' => $series->getOwner()->getFname(),
                        'titre'     => $series->getTitle(),
                        'statut'    => 'approuvee',
                    ]);
                $mailer->send($email);
            } catch (\Exception $e) {
                // L'approbation est enregistrée même si l'email échoue
                $this->addFlash('warning', 'Réservation approuvée, mais l\'email de notification n\'a pas pu être envoyé.');
            }
        }
        return $this->redirectToRoute('admin_reservation_pending');
    }

// REFUSER (raison postée depuis un modal)
    #[Route('/{id}/reject', name: 'reject', methods: ['POST'])]
    public function reject(ReservationSeries $series, Request $request, ReservationWorkflow $wf, MailerInterface $mailer): Response
    {
        // Un admin ressource ne peut refuser que sur SES ressources.
        $this->denyAccessUnlessGranted(ReservationSeriesVoter::MANAGE, $series);

        if ($this->container->has('security.csrf.token_manager') && !$this->isCsrfTokenValid('reject'.$series->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('CSRF invalid');
        }
        $reason = trim((string)$request->request->get('reason', ''));
        if ($reason === '') {
            $this->addFlash('warning', 'Une raison est requise pour refuser.');
            return $this->redirectToRoute('admin_reservation_pending');
        }
        try {
            $wf->ensureAllowed('reject', $series);
            /** @var User|null $actor */
            $actor = $this->getUser();
            // Le motif est désormais persisté dans le journal d'audit (reservation_audit_logs).
            $wf->apply('reject', $series, $actor, $reason);
            $this->addFlash('success', 'Réservation refusée.');
        } catch (\DomainException $e) {
            $this->addFlash('warning', $e->getMessage());
            return $this->redirectToRoute('admin_reservation_pending');
        }

        // --- ENVOI DE L'EMAIL (séparé pour ne pas bloquer en cas d'erreur SMTP) ---
        if ($series->getOwner() && $series->getOwner()->getEmail()) {
            try {
                $email = (new TemplatedEmail())
                    ->from(new Address('ne-pas-repondre@ton-domaine.fr', 'Service Logistique'))
                    ->to($series->getOwner()->getEmail())
                    ->subject('❌ Réservation refusée : ' . $series->getTitle())
                    ->htmlTemplate('emails/reservation_status.html.twig')
                    ->context([
                        'demandeur' => $series->getOwner()->getFname(),
                        'titre'     => $series->getTitle(),
                        'statut'    => 'refusee',
                        'motif'     => $reason,
                    ]);
                $mailer->send($email);
            } catch (\Exception $e) {
                $this->addFlash('warning', 'Réservation refusée, mais l\'email de notification n\'a pas pu être envoyé.');
            }
        }
        // On reste "LibraBooking-friendly" : on ne stocke pas la raison (elle servira en email à l’étape 5)

        return $this->redirectToRoute('admin_reservation_pending');
    }

// ANNULER
    #[Route('/{id}/cancel', name: 'cancel', methods: ['POST'])]

    public function cancel(ReservationSeries $series, Request $request, ReservationWorkflow $wf): Response
    {
        // Un admin ressource ne peut annuler que sur SES ressources.
        $this->denyAccessUnlessGranted(ReservationSeriesVoter::MANAGE, $series);

        if ($this->container->has('security.csrf.token_manager') && !$this->isCsrfTokenValid('cancel'.$series->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('CSRF invalid');
        }
       try {
            $wf->ensureAllowed('cancel', $series);
            /** @var User|null $actor */
            $actor = $this->getUser();
            $reason = trim((string) $request->request->get('reason', '')) ?: null;
            $wf->apply('cancel', $series, $actor, $reason);
            $this->addFlash('success', 'Réservation annulée.');
       } catch (\DomainException $e) {
            $this->addFlash('warning', $e->getMessage());
       }
        return $this->redirectToRoute('admin_reservation_pending');
    }
}
