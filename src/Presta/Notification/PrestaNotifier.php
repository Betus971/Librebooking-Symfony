<?php

namespace App\Presta\Notification;

use App\Presta\Entity\Inscription;
use App\Service\IcsGeneratorService;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * Notifications e-mail des rendez-vous « prestation » (module Presta).
 *
 * Modelé sur {@see \App\Notification\ReservationNotifier} : même expéditeur
 * (MAILER_FROM_ADDRESS / MAILER_FROM_NAME), envoi asynchrone via Messenger.
 * Un fichier .ics (METHOD:REQUEST) est joint pour les RDV confirmés/validés,
 * façon Doctolib.
 *
 * L'e-mail n'est envoyé qu'aux inscriptions rattachées à un client possédant
 * une adresse (les RDV « manuels » à nom libre n'ont pas de destinataire).
 */
final class PrestaNotifier
{
    public function __construct(
        private MailerInterface $mailer,
        private IcsGeneratorService $icsGenerator,
        #[Autowire('%env(MAILER_FROM_ADDRESS)%')]
        private string $fromAddress,
        #[Autowire('%env(MAILER_FROM_NAME)%')]
        private string $fromName,
    ) {
    }

    /** RDV confirmé immédiatement (prestation sans approbation). */
    public function confirmed(Inscription $inscription): void
    {
        $this->send($inscription, 'Rendez-vous confirmé : ' . $this->libelle($inscription), 'confirmee', true);
    }

    /** RDV validé par le prestataire (après approbation). */
    public function approved(Inscription $inscription): void
    {
        $this->send($inscription, 'Rendez-vous validé : ' . $this->libelle($inscription), 'validee', true);
    }

    /** Rappel 24h avant. */
    public function reminder(Inscription $inscription): void
    {
        $this->send($inscription, 'Rappel de rendez-vous demain : ' . $this->libelle($inscription), 'rappel', true);
    }

    /**
     * RDV annulé / refusé par le prestataire.
     * @param bool $wasPending true si la demande était encore « en attente »
     *                         (→ libellé « refusée » plutôt qu'« annulée »).
     */
    public function cancelledByProvider(Inscription $inscription, bool $wasPending = false): void
    {
        $subject = $wasPending
            ? 'Demande de rendez-vous refusée : ' . $this->libelle($inscription)
            : 'Rendez-vous annulé : ' . $this->libelle($inscription);

        $this->send($inscription, $subject, $wasPending ? 'refusee' : 'annulee', false);
    }

    private function send(Inscription $inscription, string $subject, string $statut, bool $attachIcs): void
    {
        $client  = $inscription->getClient();
        $session = $inscription->getSession();
        if (null === $client || !$client->getEmail() || null === $session) {
            return;
        }

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, $this->fromName))
            ->to($client->getEmail())
            ->subject($subject)
            ->htmlTemplate('emails/presta_status.html.twig')
            ->context([
                'statut'  => $statut,
                'session' => $session,
                'client'  => $client,
            ]);

        if ($attachIcs) {
            $ics = $this->icsGenerator->generateForPrestaSession($session);
            // METHOD:REQUEST → le client mail propose « Ajouter à l'agenda ».
            $ics = str_replace('METHOD:PUBLISH', 'METHOD:REQUEST', $ics);

            $filename = 'rdv-' . ($session->getService()?->getLibelle() ?? 'presta') . '.ics';
            $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename);

            $email->attach($ics, $filename, 'text/calendar; charset=utf-8; method=REQUEST');
        }

        $this->mailer->send($email);
    }

    private function libelle(Inscription $inscription): string
    {
        return $inscription->getSession()?->getService()?->getLibelle() ?? 'Prestation';
    }
}
