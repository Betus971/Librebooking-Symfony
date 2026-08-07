<?php

namespace App\Presta\Controller\Provider;

use App\Presta\Entity\PrestaAbsence;
use App\Presta\Form\PrestaAbsenceType;
use App\Presta\Repository\PrestaAbsenceRepository;
use App\Presta\Service\PrestataireResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/presta/provider/absence', name: 'app_presta_provider_absence_')]
#[IsGranted('ROLE_PRESTATAIRE')]
class AbsenceController extends AbstractController
{
    public function __construct(
        private readonly PrestataireResolver $prestataireResolver,
        private readonly PrestaAbsenceRepository $absences,
    ) {
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $prestataire = $this->prestataireResolver->getForCurrentUser();

        return $this->render('presta/provider/absence/index.html.twig', [
            'absences' => $this->absences->findByPrestataire($prestataire),
            'current_menu' => 'absence',
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $prestataire = $this->prestataireResolver->getForCurrentUser();

        $absence = new PrestaAbsence();
        $absence->setPrestataire($prestataire);

        $form = $this->createForm(PrestaAbsenceType::class, $absence);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($absence->getDateDebut() >= $absence->getDateFin()) {
                $this->addFlash('error', 'La date de fin doit être postérieure à la date de début.');
            } else {
                $this->absences->save($absence, true);

                $this->addFlash('success', 'Votre période d\'absence a été ajoutée.');

                return $this->redirectToRoute('app_presta_provider_absence_index');
            }
        }

        return $this->render('presta/provider/absence/new.html.twig', [
            'absence' => $absence,
            'form' => $form,
            'current_menu' => 'absence',
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, PrestaAbsence $absence): Response
    {
        $prestataire = $this->prestataireResolver->getForCurrentUser();

        if ($absence->getPrestataire() !== $prestataire) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à supprimer cette absence.');
        }

        if ($this->isCsrfTokenValid('delete'.$absence->getId(), $request->request->get('_token'))) {
            $this->absences->remove($absence, true);
            $this->addFlash('success', 'La période d\'absence a été supprimée.');
        }

        return $this->redirectToRoute('app_presta_provider_absence_index');
    }
}
