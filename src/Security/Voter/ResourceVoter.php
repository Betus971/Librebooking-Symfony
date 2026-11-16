<?php

namespace App\Security\Voter;

use App\Entity\Resource;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Autorisation sur une Ressource.
 *
 * Principe pour MANAGE :
 *  - ROLE_SUPER_ADMIN : accès à toutes les ressources, sans condition.
 *  - ROLE_ADMIN_RESSOURCE : accès si la ressource appartient à un
 *    ResourceGroup auquel l'utilisateur est rattaché.
 *  - Tous les autres : pas de MANAGE, mais VIEW libre (lecture catalogue).
 */
final class ResourceVoter extends Voter
{
    public const MANAGE = 'MANAGE';
    public const VIEW   = 'VIEW';

    public function __construct(private readonly Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::MANAGE, self::VIEW], true)
            && $subject instanceof Resource;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        /** @var Resource $resource */
        $resource = $subject;

        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        // Lecture : toute personne connectée peut consulter le catalogue des ressources.
        if (self::VIEW === $attribute) {
            return true;
        }

        // Super-admin : droit de gestion total.
        if ($this->security->isGranted('ROLE_SUPER_ADMIN')) {
            return true;
        }

        // Gestionnaire de ressource : il gère les ressources de ses groupes.
        if ($this->security->isGranted('ROLE_ADMIN_RESSOURCE')) {
            $resGroup = $resource->getResourceGroup();
            if (null !== $resGroup && $user->getResourceGroups()->contains($resGroup)) {
                return true;
            }

            return false;
        }

        return false;
    }
}
