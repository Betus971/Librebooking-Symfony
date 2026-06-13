<?php

namespace App\Controller;

use App\Entity\Resource;
use App\Entity\User;
use App\Service\WaitlistService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class WaitlistController extends AbstractController
{
    #[Route('/waitlist/join', name: 'waitlist_join', methods: ['POST'])]
    public function join(Request $request, EntityManagerInterface $em, WaitlistService $waitlist): Response
    {
        if (!$this->isCsrfTokenValid('waitlist_join', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $resourceId = $request->request->getInt('resource');
        $startStr    = (string) $request->request->get('start');
        $endStr      = (string) $request->request->get('end');

        $resource = $resourceId ? $em->getRepository(Resource::class)->find($resourceId) : null;
        if (!$resource instanceof Resource || $startStr === '' || $endStr === '') {
            $this->addFlash('warning', 'Demande de liste d\'attente invalide.');

            return $this->redirectToRoute('reservation_new');
        }

        try {
            $start = new \DateTimeImmutable($startStr);
            $end   = new \DateTimeImmutable($endStr);
        } catch (\Exception) {
            $this->addFlash('warning', 'Dates de liste d\'attente invalides.');

            return $this->redirectToRoute('reservation_new');
        }

        /** @var User $user */
        $user = $this->getUser();
        $waitlist->join($user, $resource, $start, $end);

        $this->addFlash('success', 'Vous êtes inscrit en liste d\'attente : vous serez notifié par e-mail si ce créneau se libère.');

        return $this->redirectToRoute('reservation_new', ['resource' => $resource->getId()]);
    }
}
