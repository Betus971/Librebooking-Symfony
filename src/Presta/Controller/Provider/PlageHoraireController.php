<?php

namespace App\Presta\Controller\Provider;

use App\Presta\Entity\PlageHoraire;
use App\Presta\Form\PlageHoraireType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/presta/provider/schedule', name: 'app_presta_provider_schedule_')]
class PlageHoraireController extends AbstractController
{
    use ProviderTrait;

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $prestataire = $this->getPrestataire($em);
        $plages = $em->getRepository(PlageHoraire::class)->findBy(
            ['prestataire' => $prestataire],
            ['jourSemaine' => 'ASC', 'heureDebut' => 'ASC']
        );

        return $this->render('presta/provider/plage_horaire/index.html.twig', [
            'plages' => $plages,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $prestataire = $this->getPrestataire($em);
        $plage = new PlageHoraire();
        $plage->setPrestataire($prestataire);

        $form = $this->createForm(PlageHoraireType::class, $plage);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($plage);
            $em->flush();
            $this->addFlash('success', 'Plage horaire ajoutée avec succès.');
            return $this->redirectToRoute('app_presta_provider_schedule_index');
        }

        return $this->render('presta/provider/plage_horaire/form.html.twig', [
            'plage' => $plage,
            'form' => $form->createView(),
            'is_edit' => false,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, PlageHoraire $plageHoraire, EntityManagerInterface $em): Response
    {
        if ($plageHoraire->getPrestataire()->getId() !== $this->getPrestataire($em)->getId()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(PlageHoraireType::class, $plageHoraire);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
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
    public function delete(Request $request, PlageHoraire $plageHoraire, EntityManagerInterface $em): Response
    {
        if ($plageHoraire->getPrestataire()->getId() !== $this->getPrestataire($em)->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete'.$plageHoraire->getId(), $request->request->get('_token'))) {
            $em->remove($plageHoraire);
            $em->flush();
            $this->addFlash('success', 'Plage horaire supprimée.');
        }

        return $this->redirectToRoute('app_presta_provider_schedule_index');
    }
}
