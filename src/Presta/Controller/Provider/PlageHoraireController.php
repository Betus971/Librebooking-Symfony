<?php

namespace App\Presta\Controller\Provider;

use App\Presta\Entity\PlageHoraire;
use App\Presta\Form\PlageHoraireType;
use App\Presta\Repository\PlageHoraireRepository;
use App\Presta\Service\PrestataireResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/presta/provider/schedule', name: 'app_presta_provider_schedule_')]
#[IsGranted('ROLE_PRESTATAIRE')]
class PlageHoraireController extends AbstractController
{
    public function __construct(
        private readonly PrestataireResolver $prestataireResolver,
        private readonly PlageHoraireRepository $plages,
    ) {
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $prestataire = $this->prestataireResolver->getForCurrentUser();

        return $this->render('presta/provider/plage_horaire/index.html.twig', [
            'plages' => $this->plages->findByPrestataire($prestataire),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $prestataire = $this->prestataireResolver->getForCurrentUser();
        $plage = new PlageHoraire();
        $plage->setPrestataire($prestataire);

        $form = $this->createForm(PlageHoraireType::class, $plage);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $jours = $form->get('joursSemaine')->getData();
            if (empty($jours)) {
                $this->addFlash('error', 'Veuillez sélectionner au moins un jour.');

                return $this->redirectToRoute('app_presta_provider_schedule_new');
            }

            foreach ($jours as $jour) {
                $newPlage = clone $plage;
                $newPlage->setJourSemaine((int) $jour);
                $this->plages->save($newPlage);
            }
            $this->plages->flush();
            $this->addFlash('success', 'Plages horaires ajoutées avec succès.');

            return $this->redirectToRoute('app_presta_provider_schedule_index');
        }

        return $this->render('presta/provider/plage_horaire/form.html.twig', [
            'plage' => $plage,
            'form' => $form->createView(),
            'is_edit' => false,
        ]);
    }

    #[Route('/delete-all', name: 'delete_all', methods: ['POST'])]
    public function deleteAll(Request $request): Response
    {
        $prestataire = $this->prestataireResolver->getForCurrentUser();

        if ($this->isCsrfTokenValid('delete_all_plages', $request->request->get('_token'))) {
            $count = $this->plages->deleteAllForPrestataire($prestataire);
            $this->addFlash(
                'success',
                $count > 0
                    ? sprintf('%d plage%s horaire%s supprimée%s.', $count, $count > 1 ? 's' : '', $count > 1 ? 's' : '', $count > 1 ? 's' : '')
                    : 'Aucune plage horaire à supprimer.',
            );
        }

        return $this->redirectToRoute('app_presta_provider_schedule_index');
    }

    /**
     * Réactive (crée) une plage horaire pour un jour + une plage donnés, depuis
     * un clic sur une case blanche de la grille « semaine type ». Symétrique de
     * la fermeture (édition/suppression d'une plage verte) → toggle intuitif.
     */
    #[Route('/activate', name: 'activate', methods: ['POST'])]
    public function activate(Request $request): Response
    {
        $prestataire = $this->prestataireResolver->getForCurrentUser();

        if ($this->container->has('security.csrf.token_manager')
            && !$this->isCsrfTokenValid('activate_plage', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_presta_provider_schedule_index');
        }

        $jour     = (int) $request->request->get('jour');
        $startStr = (string) $request->request->get('start');
        $endStr   = (string) $request->request->get('end');

        // « ! » → remet tous les champs à l'époque (secondes = 0), propre pour un TIME.
        $s = \DateTime::createFromFormat('!H:i', $startStr) ?: null;
        $e = \DateTime::createFromFormat('!H:i', $endStr) ?: null;

        if (!$s || !$e || $e <= $s || $jour < 1 || $jour > 7) {
            $this->addFlash('warning', 'Plage invalide.');
            return $this->redirectToRoute('app_presta_provider_schedule_index');
        }

        // Anti-doublon : ne recrée pas une plage identique.
        $exists = (int) $this->plages->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.prestataire = :pr')
            ->andWhere('p.jourSemaine = :j')
            ->andWhere('p.heureDebut = :s')
            ->andWhere('p.heureFin = :e')
            ->setParameter('pr', $prestataire)
            ->setParameter('j', $jour)
            ->setParameter('s', $s)
            ->setParameter('e', $e)
            ->getQuery()->getSingleScalarResult();

        if ($exists === 0) {
            $plage = (new PlageHoraire())
                ->setPrestataire($prestataire)
                ->setJourSemaine($jour)
                ->setHeureDebut($s)
                ->setHeureFin($e);
            $this->plages->save($plage, true);
            $this->addFlash('success', 'Plage réactivée.');
        } else {
            $this->addFlash('info', 'Cette plage est déjà active.');
        }

        return $this->redirectToRoute('app_presta_provider_schedule_index');
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, PlageHoraire $plageHoraire): Response
    {
        $this->denyUnlessOwner($plageHoraire);

        $form = $this->createForm(PlageHoraireType::class, $plageHoraire, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->plages->save($plageHoraire, true);
            $this->addFlash('success', 'La plage horaire a été modifiée avec succès.');

            return $this->redirectToRoute('app_presta_provider_schedule_index');
        }

        return $this->render('presta/provider/plage_horaire/form.html.twig', [
            'plage' => $plageHoraire,
            'form' => $form->createView(),
            'is_edit' => true,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, PlageHoraire $plageHoraire): Response
    {
        $this->denyUnlessOwner($plageHoraire);

        if ($this->isCsrfTokenValid('delete'.$plageHoraire->getId(), $request->request->get('_token'))) {
            $this->plages->remove($plageHoraire, true);
            $this->addFlash('success', 'Plage horaire supprimée.');
        }

        return $this->redirectToRoute('app_presta_provider_schedule_index');
    }

    private function denyUnlessOwner(PlageHoraire $plage): void
    {
        if ($plage->getPrestataire()->getId() !== $this->prestataireResolver->getForCurrentUser()->getId()) {
            throw $this->createAccessDeniedException();
        }
    }
}
