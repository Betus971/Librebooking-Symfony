<?php

namespace App\Presta\Command;

use App\Presta\Entity\Inscription;
use App\Presta\Notification\PrestaNotifier;
use App\Presta\Repository\InscriptionRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:presta:reminders',
    description: 'Envoie un rappel aux clients pour les rendez-vous de demain',
)]
class SendRemindersCommand extends Command
{
    public function __construct(
        private readonly InscriptionRepository $inscriptionRepository,
        private readonly PrestaNotifier $notifier,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $tomorrowStart = new \DateTime('tomorrow');
        $tomorrowEnd = (clone $tomorrowStart)->setTime(23, 59, 59);

        // Trouver toutes les inscriptions confirmées pour des sessions commençant demain
        $qb = $this->inscriptionRepository->createQueryBuilder('i')
            ->join('i.session', 's')
            ->where('i.statut = :status')
            ->andWhere('s.dateDebut >= :start')
            ->andWhere('s.dateDebut <= :end')
            ->setParameter('status', Inscription::STATUT_CONFIRMED)
            ->setParameter('start', $tomorrowStart)
            ->setParameter('end', $tomorrowEnd);

        $inscriptions = $qb->getQuery()->getResult();

        $count = 0;
        foreach ($inscriptions as $inscription) {
            try {
                $this->notifier->reminder($inscription);
                $count++;
            } catch (\Throwable $e) {
                $io->error('Erreur lors de l\'envoi du rappel pour l\'inscription ' . $inscription->getId() . ': ' . $e->getMessage());
            }
        }

        $io->success(sprintf('%d rappel(s) envoyé(s) avec succès.', $count));

        return Command::SUCCESS;
    }
}
