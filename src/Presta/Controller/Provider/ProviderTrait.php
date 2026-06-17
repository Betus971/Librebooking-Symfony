<?php

namespace App\Presta\Controller\Provider;

use App\Presta\Entity\Prestataire;
use Doctrine\ORM\EntityManagerInterface;

trait ProviderTrait
{
    private function getPrestataire(EntityManagerInterface $em): Prestataire
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        if (!$user) {
            // Bug fix (9 juin 2026) : retrait du `clone` inutile et incorrect
            // (on ne clone pas une exception qu'on lève). Pouvait casser les
            // instanceof côté handler global d'erreurs.
            throw $this->createAccessDeniedException('Vous devez être connecté.');
        }

        $prestataire = $em->getRepository(Prestataire::class)->findOneBy(['user' => $user]);

        if (!$prestataire) {
            $prestataire = new Prestataire();
            $prestataire->setUser($user);
            $prestataire->setNom($user->getLname() ?? 'Nom inconnu');
            $prestataire->setPrenom($user->getFname() ?? 'Prénom inconnu');
            $em->persist($prestataire);
            $em->flush();
        }

        return $prestataire;
    }
}
