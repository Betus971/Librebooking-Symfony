<?php

namespace App\Presta\Controller\Provider;

use App\Presta\Entity\Inscription;
use App\Presta\Entity\PrestaAbsence;
use App\Presta\Entity\Service;
use App\Presta\Entity\Session;
use App\Presta\Notification\PrestaNotifier;
use App\Presta\Repository\PrestaAbsenceRepository;
use App\Presta\Repository\ServiceRepository;
use App\Presta\Repository\SessionRepository;
use App\Presta\Service\PrestataireResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/presta/provider/agenda', name: 'app_presta_provider_agenda_')]
#[IsGranted('ROLE_PRESTATAIRE')]
class AgendaController extends AbstractController
{
    public function __construct(
        private readonly PrestataireResolver $prestataireResolver,
        private readonly SessionRepository $sessions,
        private readonly PrestaNotifier $prestaNotifier,
    ) {
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(PrestaAbsenceRepository $absences): Response
    {
        $prestataire = $this->prestataireResolver->getForCurrentUser();

        // Sessions futures du prestataire (1 requête, dans le repo) :
        // toutes les séances de groupe + les RDV individuels ayant ≥ 1 inscrit.
        // Le filtrage (recherche / type) se fait côté client sur le tableau.
        $allSessions = $this->sessions->findUpcomingForAgenda($prestataire);

        return $this->render('presta/provider/agenda/index.html.twig', [
            'sessions'  => $allSessions,
            'absences'  => $absences->findUpcomingForPrestataire($prestataire),
        ]);
    }

    /**
     * Blocage PONCTUEL d'un créneau : crée une absence sur une date/heure
     * précise. Ne touche PAS la semaine type (les autres semaines restent
     * ouvertes). Le générateur de créneaux retire ce créneau uniquement ce jour.
     */
    #[Route('/block', name: 'block', methods: ['POST'])]
    public function blockSlot(Request $request, PrestaAbsenceRepository $absences): Response
    {
        $prestataire = $this->prestataireResolver->getForCurrentUser();

        if (!$this->isCsrfTokenValid('block_slot', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_presta_provider_agenda_index');
        }

        $date  = (string) $request->request->get('date');
        $debut = (string) $request->request->get('heureDebut');
        $fin   = (string) $request->request->get('heureFin');
        $motif = trim((string) $request->request->get('motif', '')) ?: null;

        $tz    = new \DateTimeZone('Europe/Paris');
        $start = \DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $debut, $tz);
        $end   = \DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $fin, $tz);

        if (!$start || !$end || $end <= $start) {
            $this->addFlash('error', 'Créneau invalide : vérifiez la date et les heures (fin après début).');
            return $this->redirectToRoute('app_presta_provider_agenda_index');
        }

        $absence = (new PrestaAbsence())
            ->setPrestataire($prestataire)
            ->setDateDebut($start)
            ->setDateFin($end)
            ->setMotif($motif ?? 'Créneau bloqué');
        $absences->save($absence, true);

        $this->addFlash('success', sprintf(
            'Créneau bloqué le %s de %s à %s (ce jour uniquement).',
            $start->format('d/m/Y'), $debut, $fin
        ));

        return $this->redirectToRoute('app_presta_provider_agenda_index');
    }

    /**
     * Débloquer un créneau (supprime l'absence ponctuelle) depuis l'agenda,
     * puis revient à l'agenda.
     */
    #[Route('/block/{id}/delete', name: 'block_delete', methods: ['POST'])]
    public function blockDelete(Request $request, PrestaAbsence $absence, PrestaAbsenceRepository $absences): Response
    {
        $prestataire = $this->prestataireResolver->getForCurrentUser();
        if ($absence->getPrestataire() !== $prestataire) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à débloquer ce créneau.');
        }

        // CSRF tolérant (comme les autres routes de suppression de l'appli) : on
        // valide si le gestionnaire de jetons est présent, sinon on laisse passer
        // — évite tout blocage silencieux si le CSRF est désactivé (contexte SSO).
        if (!$this->container->has('security.csrf.token_manager')
            || $this->isCsrfTokenValid('unblock'.$absence->getId(), (string) $request->request->get('_token'))) {
            $absences->remove($absence, true);
            $this->addFlash('success', 'Créneau débloqué.');
        }

        return $this->redirectToRoute('app_presta_provider_agenda_index');
    }

    /**
     * RDV « imprévu » créé à la main par le prestataire (ex. un gradé de passage
     * à 17h), en dehors des disponibilités habituelles. On crée une séance
     * individuelle avec nbInscrits = 1 (donc visible à l'agenda ET bloquante
     * anti double-réservation) et un nom de client saisi librement.
     */
    #[Route('/rdv/new', name: 'rdv_new', methods: ['GET', 'POST'])]
    public function newRdv(Request $request, ServiceRepository $serviceRepo): Response
    {
        $prestataire = $this->prestataireResolver->getForCurrentUser();

        $services = $serviceRepo->findBy(
            ['prestataire' => $prestataire, 'type' => Service::TYPE_INDIVIDUEL, 'isActive' => true],
            ['libelle' => 'ASC']
        );

        if (!$request->isMethod('POST')) {
            return $this->render('presta/provider/agenda/rdv_new.html.twig', ['services' => $services]);
        }

        if (!$this->isCsrfTokenValid('rdv_new', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_presta_provider_agenda_rdv_new');
        }

        $serviceId = (int) $request->request->get('service');
        $service   = null;
        foreach ($services as $s) {
            if ($s->getId() === $serviceId) {
                $service = $s;
            }
        }

        $clientNom = trim((string) $request->request->get('clientNom'));
        $date      = (string) $request->request->get('date');
        $heure     = (string) $request->request->get('heure');
        $note      = trim((string) $request->request->get('note', '')) ?: null;

        $tz    = new \DateTimeZone('Europe/Paris');
        $start = \DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $heure, $tz);

        $errors = [];
        if (!$service) {
            $errors[] = 'Choisissez une prestation.';
        }
        if ($clientNom === '') {
            $errors[] = 'Indiquez le nom du client.';
        }
        if (!$start) {
            $errors[] = 'Date ou heure invalide.';
        }

        if ($errors) {
            foreach ($errors as $e) {
                $this->addFlash('error', $e);
            }
            return $this->render('presta/provider/agenda/rdv_new.html.twig', ['services' => $services]);
        }

        $end = (clone $start)->modify('+' . $service->getDureeMinutes() . ' minutes');

        $session = (new Session())
            ->setPrestataire($prestataire)
            ->setService($service)
            ->setDateDebut($start)
            ->setDateFin($end)
            ->setNbInscrits(1)
            ->setClientNom($clientNom)
            ->setNote($note);
        $this->sessions->save($session, true);

        $this->addFlash('success', sprintf(
            'RDV « %s » ajouté le %s à %s pour %s.',
            $service->getLibelle(), $start->format('d/m/Y'), $heure, $clientNom
        ));

        return $this->redirectToRoute('app_presta_provider_agenda_index');
    }

    /**
     * Modification d'un RDV « imprévu » (créé à la main). Réutilise le même
     * écran que la création, pré-rempli. Ne concerne que les RDV manuels
     * (clientNom renseigné) : les séances de groupe et les réservations client
     * passent par d'autres écrans.
     */
    #[Route('/rdv/{id}/edit', name: 'rdv_edit', methods: ['GET', 'POST'])]
    public function rdvEdit(Request $request, Session $session, ServiceRepository $serviceRepo): Response
    {
        $prestataire = $this->prestataireResolver->getForCurrentUser();
        if ($session->getPrestataire()?->getId() !== $prestataire->getId() || $session->getClientNom() === null) {
            throw $this->createAccessDeniedException('Ce rendez-vous ne peut pas être modifié ici.');
        }

        $services = $serviceRepo->findBy(
            ['prestataire' => $prestataire, 'type' => Service::TYPE_INDIVIDUEL, 'isActive' => true],
            ['libelle' => 'ASC']
        );

        if (!$request->isMethod('POST')) {
            return $this->render('presta/provider/agenda/rdv_new.html.twig', [
                'services' => $services,
                'session'  => $session,
                'is_edit'  => true,
            ]);
        }

        if (!$this->isCsrfTokenValid('rdv_edit'.$session->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_presta_provider_agenda_rdv_edit', ['id' => $session->getId()]);
        }

        $serviceId = (int) $request->request->get('service');
        $service   = null;
        foreach ($services as $s) {
            if ($s->getId() === $serviceId) {
                $service = $s;
            }
        }

        $clientNom = trim((string) $request->request->get('clientNom'));
        $date      = (string) $request->request->get('date');
        $heure     = (string) $request->request->get('heure');
        $note      = trim((string) $request->request->get('note', '')) ?: null;

        $tz    = new \DateTimeZone('Europe/Paris');
        $start = \DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $heure, $tz);

        $errors = [];
        if (!$service)         { $errors[] = 'Choisissez une prestation.'; }
        if ($clientNom === '') { $errors[] = 'Indiquez le nom du client.'; }
        if (!$start)           { $errors[] = 'Date ou heure invalide.'; }

        if ($errors) {
            foreach ($errors as $e) {
                $this->addFlash('error', $e);
            }
            return $this->render('presta/provider/agenda/rdv_new.html.twig', [
                'services' => $services,
                'session'  => $session,
                'is_edit'  => true,
            ]);
        }

        $end = (clone $start)->modify('+' . $service->getDureeMinutes() . ' minutes');

        $session->setService($service)
            ->setDateDebut($start)
            ->setDateFin($end)
            ->setClientNom($clientNom)
            ->setNote($note);
        $this->sessions->save($session, true);

        $this->addFlash('success', sprintf(
            'RDV « %s » mis à jour le %s à %s pour %s.',
            $service->getLibelle(), $start->format('d/m/Y'), $heure, $clientNom
        ));

        return $this->redirectToRoute('app_presta_provider_agenda_index');
    }

    /**
     * Suppression d'un RDV « imprévu » (créé à la main) depuis l'agenda.
     * Réservé aux RDV manuels (clientNom renseigné) pour éviter de supprimer par
     * mégarde une séance avec de vrais inscrits.
     */
    #[Route('/rdv/{id}/delete', name: 'rdv_delete', methods: ['POST'])]
    public function rdvDelete(Request $request, Session $session): Response
    {
        $prestataire = $this->prestataireResolver->getForCurrentUser();
        if ($session->getPrestataire()?->getId() !== $prestataire->getId() || $session->getClientNom() === null) {
            throw $this->createAccessDeniedException('Ce rendez-vous ne peut pas être supprimé ici.');
        }

        if ($this->isCsrfTokenValid('rdv_delete'.$session->getId(), (string) $request->request->get('_token'))) {
            $this->sessions->remove($session, true);
            $this->addFlash('success', 'Rendez-vous supprimé.');
        }

        return $this->redirectToRoute('app_presta_provider_agenda_index');
    }

    /**
     * Validation d'un RDV « en attente » (prestation à approbation) par le
     * prestataire → passe l'inscription en CONFIRMED.
     */
    #[Route('/inscription/{id}/approve', name: 'approve_inscription', methods: ['POST'])]
    public function approveInscription(Request $request, Inscription $inscription): Response
    {
        $session = $inscription->getSession();
        $prestataire = $this->prestataireResolver->getForCurrentUser();

        if ($session->getPrestataire() !== $prestataire) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à valider cette réservation.');
        }

        if ($this->isCsrfTokenValid('approve'.$inscription->getId(), $request->request->get('_token'))) {
            if ($inscription->isPending()) {
                $inscription->setStatut(Inscription::STATUT_CONFIRMED);
                $this->sessions->save($session, true);
                // Validation → e-mail au client + .ics.
                try { $this->prestaNotifier->approved($inscription); } catch (\Throwable) {}
                $this->addFlash('success', 'Rendez-vous validé.');
            } else {
                $this->addFlash('info', 'Ce rendez-vous n\'est plus en attente.');
            }
        }

        return $this->redirectToRoute('app_presta_provider_agenda_index');
    }

    #[Route('/inscription/{id}/cancel', name: 'cancel_inscription', methods: ['POST'])]
    public function cancelInscription(Request $request, Inscription $inscription): Response
    {
        $session = $inscription->getSession();
        $prestataire = $this->prestataireResolver->getForCurrentUser();

        // Sécurité : la séance doit appartenir au prestataire courant.
        if ($session->getPrestataire() !== $prestataire) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à annuler cette réservation.');
        }

        if ($this->isCsrfTokenValid('cancel'.$inscription->getId(), $request->request->get('_token'))) {
            $etaitEnAttente = $inscription->isPending();
            $inscription->setStatut(Inscription::STATUT_CANCELLED);
            $session->setNbInscrits(max(0, $session->getNbInscrits() - 1));

            // Entités déjà managées : un simple flush via le repo suffit.
            $this->sessions->save($session, true);
            // Prévenir le client (refus si c'était en attente, sinon annulation).
            try { $this->prestaNotifier->cancelledByProvider($inscription, $etaitEnAttente); } catch (\Throwable) {}
            $this->addFlash('success', $etaitEnAttente
                ? 'La demande de rendez-vous a été refusée.'
                : 'La réservation a été annulée avec succès.');
        }

        return $this->redirectToRoute('app_presta_provider_agenda_index');
    }

    #[Route('/session/{id}/move', name: 'move_session', methods: ['POST'])]
    public function moveSession(Request $request, Session $session): Response
    {
        $prestataire = $this->prestataireResolver->getForCurrentUser();
        if ($session->getPrestataire() !== $prestataire) {
            return $this->json(['error' => 'Non autorisé'], 403);
        }

        $payload = json_decode($request->getContent(), true);
        $newStart = null;
        $newEnd = null;

        if (isset($payload['start']) && isset($payload['end'])) {
            try {
                $newStart = new \DateTime($payload['start']);
                $newEnd = new \DateTime($payload['end']);
            } catch (\Exception $e) {
                return $this->json(['error' => 'Format de date invalide'], 400);
            }
        }

        if ($newStart && $newEnd) {
            $session->setDateDebut($newStart);
            $session->setDateFin($newEnd);
            $this->sessions->save($session, true);
            
            // Note: En phase 2, on pourrait notifier les inscrits de ce décalage.
            return $this->json(['success' => true]);
        }

        return $this->json(['error' => 'Données manquantes'], 400);
    }
}
