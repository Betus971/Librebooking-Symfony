<?php

namespace App\Command;

use App\Notification\ReservationNotifier;
use App\Repository\ReservationInstanceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Envoie les rappels e-mail pour les réservations approuvées dont le début
 * tombe dans la fenêtre de rappel. À planifier en cron (ex. toutes les 15 min) :
 *
 *   php bin/console app:reservations:send-reminders
 */
#[AsCommand(
    name: 'app:reservations:send-reminders',
    description: 'Envoie les rappels e-mail des réservations à venir.'
)]
final class SendRemindersCommand extends Command
{
    public function __construct(
        private readonly ReservationInstanceRepository $instances,
        private readonly ReservationNotifier $notifier,
        private readonly EntityManagerInterface $em,
        #[Autowire(param: 'app.reminder.lead_minutes')]
        private readonly int $leadMinutes = 1440,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $now   = new \DateTimeImmutable();
        $until = $now->modify(sprintf('+%d minutes', $this->leadMinutes));

        $instances = $this->instances->findInstancesToRemind($now, $until);

        $sent = 0;
        foreach ($instances as $instance) {
            $series = $instance->getSeries();
            if (null === $series) {
                continue;
            }

            $this->notifier->reminder($series, $instance);
            $instance->setReminderSentAt($now);
            ++$sent;
        }

        if ($sent > 0) {
            $this->em->flush();
        }

        $io->success(sprintf('%d rappel(s) envoyé(s).', $sent));

        return Command::SUCCESS;
    }
}
