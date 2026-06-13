<?php

namespace App\Command;

use App\Domain\Reservation\ReservationWorkflow;
use App\Notification\ReservationNotifier;
use App\Repository\ReservationInstanceRepository;
use App\Service\WaitlistService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Libère automatiquement les réservations non pointées : si une ressource a
 * le check-in activé et un délai `auto_release_minutes`, et que le créneau a
 * démarré depuis plus longtemps que ce délai sans check-in, la réservation est
 * annulée (le créneau redevient disponible, la liste d'attente est notifiée).
 *
 * À planifier en cron (ex. toutes les 5 min) :
 *   php bin/console app:reservations:auto-release
 */
#[AsCommand(
    name: 'app:reservations:auto-release',
    description: 'Annule les réservations approuvées non pointées après le délai de libération.'
)]
final class AutoReleaseCommand extends Command
{
    public function __construct(
        private readonly ReservationInstanceRepository $instances,
        private readonly ReservationWorkflow $workflow,
        private readonly ReservationNotifier $notifier,
        private readonly WaitlistService $waitlist,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io  = new SymfonyStyle($input, $output);
        $now = new \DateTimeImmutable();

        $released  = 0;
        $processed = [];

        foreach ($this->instances->findCheckinCandidates($now) as $instance) {
            $series = $instance->getSeries();
            if (null === $series || isset($processed[$series->getId()])) {
                continue;
            }

            // Délai de libération de la (première) ressource de la série.
            $minutes = null;
            foreach ($series->getReservationResources() as $rr) {
                $resource = $rr->getResource();
                if ($resource && null !== $resource->getAutoReleaseMinutes()) {
                    $minutes = (int) $resource->getAutoReleaseMinutes();
                    break;
                }
            }
            if (null === $minutes || $minutes <= 0) {
                continue;
            }

            $threshold = (new \DateTimeImmutable($instance->getStartDate()->format('Y-m-d H:i:s')))
                ->modify(sprintf('+%d minutes', $minutes));

            if ($threshold >= $now) {
                continue; // délai pas encore écoulé
            }

            try {
                $this->workflow->apply('cancel', $series, null);
                $this->notifier->cancelled($series);
                $this->waitlist->notifyForFreedSeries($series);
                $processed[$series->getId()] = true;
                ++$released;
            } catch (\LogicException) {
                // déjà dans un statut non annulable : on ignore.
            }
        }

        $io->success(sprintf('%d réservation(s) libérée(s).', $released));

        return Command::SUCCESS;
    }
}
