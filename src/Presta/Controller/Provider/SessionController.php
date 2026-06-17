<?php

namespace App\Presta\Controller\Provider;

use App\Presta\Entity\Prestataire;
use App\Presta\Entity\Session;
use App\Presta\Form\SessionType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/presta/provider/session', name: 'app_presta_provider_session_')]
class SessionController extends AbstractController
{
    use ProviderTrait;

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $prestataire = $this->getPrestataire($em);
        $sessions = $em->getRepository(Session::class)->findBy(
            ['prestataire' => $prestataire],
            ['dateDebut' => 'ASC']
        );

        return $this->render('presta/provider/session/index.html.twig', [
            'sessions' => $sessions,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $prestataire = $this->getPrestataire($em);
        $session = new Session();
        $session->setPrestataire($prestataire);

        $form = $this->createForm(SessionType::class, $session, [
            'prestataire' => $prestataire,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($session);
            $em->flush();
            $this->addFlash('success', 'La séance a été planifiée avec succès.');
            return $this->redirectToRoute('app_presta_provider_session_index');
        }

        return $this->render('presta/provider/session/form.html.twig', [
            'session' => $session,
            'form' => $form->createView(),
            'is_edit' => false,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Session $session, EntityManagerInterface $em): Response
    {
        // Bug fix (9 juin 2026) : la vérification était hardcodée à
        // "id !== 1" → seul le prestataire #1 pouvait éditer ses propres
        // séances, tous les autres se prenaient un 403. On compare maintenant
        // avec le prestataire courant (même logique que delete() ci-dessous).
        $prestataire = $session->getPrestataire();
        if ($prestataire->getId() !== $this->getPrestataire($em)->getId()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(SessionType::class, $session, [
            'prestataire' => $prestataire,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'La séance a été modifiée avec succès.');
            return $this->redirectToRoute('app_presta_provider_session_index');
        }

        return $this->render('presta/provider/session/form.html.twig', [
            'session' => $session,
            'form' => $form->createView(),
            'is_edit' => true,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Session $session, EntityManagerInterface $em): Response
    {
        if ($session->getPrestataire()->getId() !== $this->getPrestataire($em)->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete'.$session->getId(), $request->request->get('_token'))) {
            $em->remove($session);
            $em->flush();
            $this->addFlash('success', 'Séance supprimée.');
        }

        return $this->redirectToRoute('app_presta_provider_session_index');
    }
}
