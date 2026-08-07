<?php

namespace App\Presta\Service;

use App\Entity\User;
use App\Presta\Entity\Inscription;
use App\Presta\Entity\Service;
use App\Presta\Entity\Session;
use App\Presta\Repository\InscriptionRepository;
use App\Presta\Repository\SessionRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Confirme un rendez-vous individuel de façon SÛRE vis-à-vis de la concurrence.
 *
 * Sans précaution, deux clients (ou un double-clic) pouvaient réserver le même
 * créneau : le calcul des créneaux libres se fait à l'affichage, mais rien ne
 * re-vérifiait la disponibilité au moment d'enregistrer.
 *
 * On reproduit ici le mécanisme déjà utilisé pour la réservation de ressources
 * (cf. ReservationManager) :
 *   - verrou `pg_advisory_xact_lock` sérialisant par PRESTATAIRE ;
 *   - re-check de conflit SOUS verrou (ferme la fenêtre de course) ;
 *   - création séance + inscription dans une transaction unique.
 */
final class IndividualBookingManager
{
    /** Espace de noms du verrou Postgres : "PRST" (distinct de "RESV"). */
    private const LOCK_NAMESPACE = 0x50525354;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SessionRepository $sessions,
        private readonly InscriptionRepository $inscriptions,
    ) {
    }

    /**
     * Tente de réserver le créneau commençant à $start pour $client.
     *
     * Si la prestation exige une approbation, l'inscription est créée en
     * « en attente » (PENDING) — le créneau reste bloqué en attendant la
     * décision du prestataire. Sinon elle est « confirmée » (CONFIRMED).
     *
     * @return Inscription|null l'inscription créée ; null si le créneau vient
     *                          d'être pris entre l'affichage et la confirmation.
     *
     * @throws \DomainException 'active_booking_exists' si le prestataire limite à
     *                          un seul RDV actif par client et que le client en a
     *                          déjà un (en attente ou confirmé, non passé).
     */
    public function book(Service $service, User $client, \DateTimeInterface $start): ?Inscription
    {
        $end = (clone $start)->modify('+' . (int) $service->getDureeMinutes() . ' minutes');
        $prestataire = $service->getPrestataire();

        return $this->em->wrapInTransaction(function () use ($service, $client, $start, $end, $prestataire): ?Inscription {
            // Verrou par prestataire : auto-libéré en fin de transaction.
            $this->em->getConnection()->executeStatement(
                'SELECT pg_advisory_xact_lock(:ns, :rid)',
                ['ns' => self::LOCK_NAMESPACE, 'rid' => (int) $prestataire->getId()],
            );

            // Règle « un seul RDV actif à la fois » (option prestataire), vérifiée
            // SOUS verrou pour être insensible aux doubles-clics / requêtes
            // concurrentes. Ne concerne que les RDV individuels.
            if ($prestataire->isUnRdvActifParClient()
                && $this->inscriptions->hasActiveIndividualBooking($client, $prestataire)) {
                throw new \DomainException('active_booking_exists');
            }

            // Re-check SOUS verrou : une autre requête a pu insérer entre
            // l'affichage des créneaux et l'acquisition du lock.
            if ($this->sessions->hasConflictForPrestataire($prestataire, $start, $end)) {
                return null;
            }

            $session = new Session();
            $session->setPrestataire($prestataire);
            $session->setService($service);
            $session->setDateDebut($start);
            $session->setDateFin($end);
            $session->setNbInscrits(1);
            $this->em->persist($session);

            $inscription = new Inscription();
            $inscription->setSession($session);
            $inscription->setClient($client);
            $inscription->setStatut(
                $service->isRequiresApproval()
                    ? Inscription::STATUT_PENDING
                    : Inscription::STATUT_CONFIRMED
            );
            $this->em->persist($inscription);

            $this->em->flush();

            return $inscription;
        });
    }
}
