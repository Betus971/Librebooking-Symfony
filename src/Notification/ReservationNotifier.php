<?php
namespace App\Notification;

use App\Entity\ReservationInstance;
use App\Entity\ReservationSeries;
use App\Entity\WaitlistRequest;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

/**
 * Envoie les e-mails liés au cycle de vie d'une réservation.
 *
 * Les échecs d'envoi (SMTP indisponible, template manquant…) sont journalisés
 * mais ne sont JAMAIS propagés : une notification ne doit pas faire échouer la
 * réservation ni l'action admin qui la déclenche.
 */
final class ReservationNotifier
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        #[Autowire(param: 'app.mail.from')]
        private string $from = 'no-reply@librebooking.local',
        private ?LoggerInterface $logger = null,
    ) {}

    public function created(ReservationSeries $s): void   { $this->send($s, 'created'); }
    public function approved(ReservationSeries $s): void  { $this->send($s, 'approved'); }
    public function cancelled(ReservationSeries $s): void { $this->send($s, 'cancelled'); }
    public function rejected(ReservationSeries $s, string $reason): void { $this->send($s, 'rejected', ['reason' => $reason]); }

    /**
     * Rappel avant le début d'une réservation. $instance est l'occurrence concernée.
     */
    public function reminder(ReservationSeries $s, ReservationInstance $instance): void
    {
        $this->send($s, 'reminder', ['instance' => $instance]);
    }

    /**
     * Notifie un demandeur en liste d'attente qu'un créneau s'est libéré.
     */
    public function waitlistOpened(WaitlistRequest $w): void
    {
        $this->dispatch(
            $w->getUser()->getEmail(),
            'emails/waitlist/opened',
            ['waitlist' => $w]
        );
    }

    private function send(ReservationSeries $s, string $tpl, array $ctx = []): void
    {
        $this->dispatch(
            $s->getOwner()?->getEmail(),
            "emails/reservation/{$tpl}",
            $ctx + ['series' => $s]
        );
    }

    /**
     * Rendu + envoi générique, tolérant aux pannes (un échec n'interrompt
     * jamais l'action métier appelante).
     *
     * @param array<string,mixed> $context
     */
    private function dispatch(?string $to, string $templateBase, array $context): void
    {
        if (!$to) {
            return; // pas de destinataire → rien à envoyer.
        }

        try {
            $subject = $this->twig->render("{$templateBase}_subject.txt.twig", $context);
            $html    = $this->twig->render("{$templateBase}.html.twig", $context);

            $email = (new Email())
                ->from($this->from)
                ->to($to)
                ->subject(trim($subject))
                ->html($html);

            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger?->error('Échec d\'envoi de notification', [
                'template'  => $templateBase,
                'exception' => $e,
            ]);
        }
    }
}
