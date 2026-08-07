<?php

namespace App\Presta\Controller\Provider;

use App\Presta\Entity\Service;
use App\Presta\Form\ServiceType;
use App\Presta\Repository\ServiceRepository;
use App\Presta\Service\PrestataireResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/presta/provider/service', name: 'app_presta_provider_service_')]
#[IsGranted('ROLE_PRESTATAIRE')]
class ServiceController extends AbstractController
{
    public function __construct(
        private readonly PrestataireResolver $prestataireResolver,
        private readonly ServiceRepository $services,
    ) {
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $prestataire = $this->prestataireResolver->getForCurrentUser();

        return $this->render('presta/provider/service/index.html.twig', [
            'services' => $this->services->findByPrestataire($prestataire),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $prestataire = $this->prestataireResolver->getForCurrentUser();
        $service = new Service();
        $service->setPrestataire($prestataire);

        $form = $this->createForm(ServiceType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->services->save($service, true);
            $this->addFlash('success', 'Prestation ajoutée au catalogue.');

            return $this->redirectToRoute('app_presta_provider_service_index');
        }

        return $this->render('presta/provider/service/form.html.twig', [
            'form' => $form->createView(),
            'is_edit' => false,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Service $service): Response
    {
        $this->denyUnlessOwner($service);

        $form = $this->createForm(ServiceType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->services->save($service, true);
            $this->addFlash('success', 'Prestation modifiée.');

            return $this->redirectToRoute('app_presta_provider_service_index');
        }

        return $this->render('presta/provider/service/form.html.twig', [
            'form' => $form->createView(),
            'is_edit' => true,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Service $service): Response
    {
        $this->denyUnlessOwner($service);

        if ($this->isCsrfTokenValid('delete' . $service->getId(), $request->request->get('_token'))) {
            $this->services->remove($service, true);
            $this->addFlash('success', 'Prestation supprimée.');
        }

        return $this->redirectToRoute('app_presta_provider_service_index');
    }

    #[Route('/{id}/toggle-active', name: 'toggle_active', methods: ['POST'])]
    public function toggleActive(Request $request, Service $service): Response
    {
        if ($service->getPrestataire()->getId() !== $this->prestataireResolver->getForCurrentUser()->getId()) {
            return $this->json(['error' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        if (!isset($data['active'])) {
            return $this->json(['error' => 'Paramètre manquant'], Response::HTTP_BAD_REQUEST);
        }

        $service->setIsActive((bool) $data['active']);
        $this->services->save($service, true);

        return $this->json(['success' => true]);
    }

    private function denyUnlessOwner(Service $service): void
    {
        if ($service->getPrestataire()->getId() !== $this->prestataireResolver->getForCurrentUser()->getId()) {
            throw $this->createAccessDeniedException();
        }
    }
}
