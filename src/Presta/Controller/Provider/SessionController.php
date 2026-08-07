<?php

namespace App\Presta\Controller\Provider;

use App\Presta\Entity\Session;
use App\Presta\Form\SessionType;
use App\Presta\Repository\SessionRepository;
use App\Presta\Service\PrestataireResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/presta/provider/session', name: 'app_presta_provider_session_')]
#[IsGranted('ROLE_PRESTATAIRE')]
class SessionController extends AbstractController
{
    public function __construct(
        private readonly PrestataireResolver $prestataireResolver,
        private readonly SessionRepository $sessions,
    ) {
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $prestataire = $this->prestataireResolver->getForCurrentUser();

        return $this->render('presta/provider/session/index.html.twig', [
            'sessions' => $this->sessions->findGroupByPrestataire($prestataire),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $prestataire = $this->prestataireResolver->getForCurrentUser();
        $session = new Session();
        $session->setPrestataire($prestataire);

        $form = $this->createForm(SessionType::class, $session, [
            'prestataire' => $prestataire,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->sessions->save($session, true);
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
    public function edit(Request $request, Session $session): Response
    {
        $prestataire = $session->getPrestataire();
        if ($prestataire->getId() !== $this->prestataireResolver->getForCurrentUser()->getId()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(SessionType::class, $session, [
            'prestataire' => $prestataire,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->sessions->save($session, true);
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
    public function delete(Request $request, Session $session): Response
    {
        if ($session->getPrestataire()->getId() !== $this->prestataireResolver->getForCurrentUser()->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete'.$session->getId(), $request->request->get('_token'))) {
            $this->sessions->remove($session, true);
            $this->addFlash('success', 'Séance supprimée.');
        }

        return $this->redirectToRoute('app_presta_provider_session_index');
    }
}
