<?php

namespace App\Presta\Controller\Provider;

use App\Presta\Entity\Prestataire;
use App\Presta\Form\PrestataireType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/presta/provider/profile', name: 'app_presta_provider_profile_')]
class ProfileController extends AbstractController
{
    use ProviderTrait;

    #[Route('/', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, EntityManagerInterface $em): Response
    {
        $prestataire = $this->getPrestataire($em);

        $form = $this->createForm(PrestataireType::class, $prestataire);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Votre profil a été mis à jour avec succès.');

            return $this->redirectToRoute('app_presta_provider_profile_edit');
        }

        return $this->render('presta/provider/profile/edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
