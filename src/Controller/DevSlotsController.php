<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DevSlotsController extends AbstractController
{
    #[Route('/dev/slots', name: 'app_dev_slots')]
    public function index(): Response
    {

        $date = (new \DateTimeImmutable('today'))->format('Y-m-d');

        return $this->render('dev_slots/index.html.twig', [
            'date'          => $date,
            'times'         => ['09:00','09:30','10:00','10:30','11:00','17:00','17:30','18:00','18:30'],
            'step_minutes'  => 30,   // durée d’un créneau
            'resource'      => ['id' => 1, 'name' => 'Guichet A'],
        ]);
    }

    #[Route('/dev/res-check', name: 'app_dev_res_check', methods: ['GET'])]
    public function check(Request $request): Response
    {
        // Simple page qui affiche ce que le modal envoie
        return $this->render('dev/check.html.twig', [
            'start'    => $request->query->get('start'),
            'end'      => $request->query->get('end'),
            'resource' => $request->query->get('resource'),
        ]);
    }
}
