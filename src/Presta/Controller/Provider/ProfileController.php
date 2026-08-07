<?php

namespace App\Presta\Controller\Provider;

use App\Presta\Form\PrestataireType;
use App\Presta\Repository\PrestataireRepository;
use App\Presta\Service\PrestataireResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/presta/provider/profile', name: 'app_presta_provider_profile_')]
#[IsGranted('ROLE_PRESTATAIRE')]
class ProfileController extends AbstractController
{
    public function __construct(
        private readonly PrestataireResolver $prestataireResolver,
        private readonly PrestataireRepository $prestataires,
    ) {
    }

    #[Route('/', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request): Response
    {
        $prestataire = $this->prestataireResolver->getForCurrentUser();

        $form = $this->createForm(PrestataireType::class, $prestataire);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->prestataires->save($prestataire, true);
            $this->addFlash('success', 'Votre profil a été mis à jour avec succès.');

            return $this->redirectToRoute('app_presta_provider_profile_edit');
        }

        return $this->render('presta/provider/profile/edit.html.twig', [
            'form' => $form->createView(),
            'prestataire' => $prestataire,
        ]);
    }

    #[Route('/ical-token', name: 'ical_token', methods: ['POST'])]
    public function generateIcalToken(Request $request): Response
    {
        $prestataire = $this->prestataireResolver->getForCurrentUser();

        if ($this->isCsrfTokenValid('generate_ical_token', (string) $request->request->get('_token'))) {
            $prestataire->setIcalToken(bin2hex(random_bytes(32)));
            $this->prestataires->save($prestataire, true);
            $this->addFlash('success', 'Lien de synchronisation iCal généré avec succès.');
        }

        return $this->redirectToRoute('app_presta_provider_profile_edit');
    }
}
