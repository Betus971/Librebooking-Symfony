<?php

namespace App\Presta\Controller\Provider;

use App\Presta\Entity\Service;
use App\Presta\Form\ServiceType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/presta/provider/service', name: 'app_presta_provider_service_')]
class ServiceController extends AbstractController
{
    use ProviderTrait;

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $prestataire = $this->getPrestataire($em);
        $services = $em->getRepository(Service::class)->findBy(['prestataire' => $prestataire]);

        return $this->render('presta/provider/service/index.html.twig', [
            'services' => $services,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $prestataire = $this->getPrestataire($em);
        $service = new Service();
        $service->setPrestataire($prestataire);

        $form = $this->createForm(ServiceType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($service);
            $em->flush();
            $this->addFlash('success', 'Prestation ajoutée au catalogue.');

            return $this->redirectToRoute('app_presta_provider_service_index');
        }

        return $this->render('presta/provider/service/form.html.twig', [
            'form' => $form->createView(),
            'is_edit' => false,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Service $service, EntityManagerInterface $em): Response
    {
        if ($service->getPrestataire()->getId() !== $this->getPrestataire($em)->getId()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(ServiceType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Prestation modifiée.');

            return $this->redirectToRoute('app_presta_provider_service_index');
        }

        return $this->render('presta/provider/service/form.html.twig', [
            'form' => $form->createView(),
            'is_edit' => true,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Service $service, EntityManagerInterface $em): Response
    {
        if ($service->getPrestataire()->getId() !== $this->getPrestataire($em)->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete_service' . $service->getId(), $request->request->get('_token'))) {
            $em->remove($service);
            $em->flush();
            $this->addFlash('success', 'Prestation supprimée.');
        }

        return $this->redirectToRoute('app_presta_provider_service_index');
    }
}
