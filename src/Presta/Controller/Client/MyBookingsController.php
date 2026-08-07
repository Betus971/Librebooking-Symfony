<?php

namespace App\Presta\Controller\Client;

use App\Entity\User;
use App\Presta\Entity\Inscription;
use App\Presta\Repository\InscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/presta/c/my-bookings', name: 'app_presta_client_my_bookings_')]
class MyBookingsController extends AbstractController
{
    private function getClient(): User
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté.');
        }
        return $user;
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(InscriptionRepository $inscriptions): Response
    {
        $client = $this->getClient();

        // Inscriptions du client, séance + service chargés (1 requête, dans le repo).
        $inscriptions = $inscriptions->findForClient($client);

        $upcoming = [];
        $past = [];
        $now = new \DateTime();

        foreach ($inscriptions as $inscription) {
            if ($inscription->getStatut() === 'CANCELLED' || $inscription->getSession()->getDateDebut() < $now) {
                $past[] = $inscription;
            } else {
                $upcoming[] = $inscription;
            }
        }

        // Trier upcoming par date croissante (plus proche d'abord)
        usort($upcoming, function (Inscription $a, Inscription $b) {
            return $a->getSession()->getDateDebut() <=> $b->getSession()->getDateDebut();
        });

        return $this->render('presta/client/my_bookings/index.html.twig', [
            'upcoming' => $upcoming,
            'past' => $past,
        ]);
    }

    #[Route('/{id}/cancel', name: 'cancel', methods: ['POST'])]
    public function cancel(Request $request, Inscription $inscription, EntityManagerInterface $em): Response
    {
        $client = $this->getClient();

        if ($inscription->getClient() !== $client) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas annuler cette réservation.');
        }

        if ($this->isCsrfTokenValid('cancel'.$inscription->getId(), $request->request->get('_token'))) {

            if ($inscription->getStatut() !== 'CANCELLED') {
                $session = $inscription->getSession();

                // Délai d'annulation : le client ne peut plus annuler à moins de
                // N heures du rendez-vous (N configuré par le prestataire).
                // Exception : une demande encore EN ATTENTE peut toujours être
                // retirée (elle n'est pas confirmée).
                $prestataire = $session->getPrestataire();
                if (!$inscription->isPending() && $prestataire && !$prestataire->annulationClientPossible($session->getDateDebut())) {
                    $this->addFlash('error', sprintf(
                        'Annulation impossible : vous ne pouvez plus annuler à moins de %d heures du rendez-vous. Merci de contacter le prestataire.',
                        $prestataire->getDelaiAnnulationHeures()
                    ));

                    return $this->redirectToRoute('app_presta_client_my_bookings_index');
                }

                $inscription->setStatut('CANCELLED');
                $session->setNbInscrits(max(0, $session->getNbInscrits() - 1));

                $em->flush();
                $this->addFlash('success', 'Votre réservation a été annulée avec succès.');
            }
        }

        return $this->redirectToRoute('app_presta_client_my_bookings_index');
    }
}
