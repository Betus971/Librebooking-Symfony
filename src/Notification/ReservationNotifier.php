<?php
namespace App\Notification;

use App\Entity\ReservationSeries;
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

    private function send(ReservationSeries $s, string $tpl, array $ctx = []): void
    {
        $to = $s->getOwner()?->getEmail();
        if (!$to) {
            return; // pas de destinataire → rien à envoyer.
        }

        try {
            $ctx += ['series' => $s];

            $subject = $this->twig->render("emails/reservation/{$tpl}_subject.txt.twig", $ctx);
            $html    = $this->twig->render("emails/reservation/{$tpl}.html.twig", $ctx);

            $email = (new Email())
                ->from($this->from)
                ->to($to)
                ->subject(trim($subject))
                ->html($html);

            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger?->error('Échec de notification de réservation', [
                'template'  => $tpl,
                'series_id' => $s->getId(),
                'exception' => $e,
            ]);
        }
    }
}
