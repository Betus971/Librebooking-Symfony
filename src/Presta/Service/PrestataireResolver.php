<?php

namespace App\Presta\Service;

use App\Entity\User;
use App\Presta\Entity\Prestataire;
use App\Presta\Repository\PrestataireRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Résout le Prestataire associé à l'utilisateur courant.
 *
 * Remplace l'ancien ProviderTrait : la logique « trouver ou créer le
 * prestataire du user connecté » est désormais un service réutilisable et
 * testable, au lieu d'être copiée dans chaque contrôleur provider.
 */
final class PrestataireResolver
{
    public function __construct(
        private readonly Security $security,
        private readonly PrestataireRepository $prestataireRepository,
    ) {
    }

    /**
     * Prestataire de l'utilisateur connecté. Le crée à la volée au premier
     * accès (provisioning), à partir de l'identité de l'utilisateur.
     */
    public function getForCurrentUser(): Prestataire
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('Vous devez être connecté.');
        }

        $prestataire = $this->prestataireRepository->findOneByUser($user);

        if (!$prestataire) {
            $prestataire = new Prestataire();
            $prestataire->setUser($user);
            $prestataire->setNom($user->getLname() ?? 'Nom inconnu');
            $prestataire->setPrenom($user->getFname() ?? 'Prénom inconnu');
            $this->prestataireRepository->save($prestataire, true);
        }

        return $prestataire;
    }
}
