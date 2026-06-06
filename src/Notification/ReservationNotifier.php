<?php
namespace App\Notification;

use App\Entity\ReservationSeries;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

final class ReservationNotifier
{
    public function __construct(private MailerInterface $mailer, private Environment $twig) {}

    public function created(ReservationSeries $s): void   { $this->send($s, 'created'); }
    public function approved(ReservationSeries $s): void  { $this->send($s, 'approved'); }
    public function cancelled(ReservationSeries $s): void { $this->send($s, 'cancelled'); }
    public function rejected(ReservationSeries $s, string $reason): void { $this->send($s, 'rejected', ['reason' => $reason]); }

    private function send(ReservationSeries $s, string $tpl, array $ctx = []): void
    {
        $ctx += ['series' => $s];

        $subject = $this->twig->render("emails/reservation/{$tpl}_subject.txt.twig", $ctx);
        $html    = $this->twig->render("emails/reservation/{$tpl}.html.twig", $ctx);

        $to = $s->getOwner()?->getEmail() ?: 'dev@local.test';

        $email = (new Email())
            ->from('no-reply@local.test')
            ->to($to)
            ->subject(trim($subject))
            ->html($html);

        $this->mailer->send($email);
    }
}
