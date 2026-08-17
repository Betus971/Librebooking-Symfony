<?php

namespace App\Security\Voter;

use App\Entity\Resource;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Autorisation "scopée" sur une Ressource — visibilité HYBRIDE (P3).
 *
 * Principe pour MANAGE :
 *  - ROLE_SUPER_ADMIN : accès à toutes les ressources, sans condition.
 *  - ROLE_ADMIN_RESSOURCE : accès si AU MOINS UNE des conditions est vraie :
 *      (a) couche SSO automatique — la ressource porte le MÊME code unité que
 *          l'utilisateur (Resource.codeUnite == User.codeunite). C'est le
 *          mécanisme PRINCIPAL : pas de gestion manuelle, suit les mutations.
 *      (b) couche manuelle d'exception — la ressource appartient à un
 *          ResourceGroup auquel l'utilisateur est rattaché. Porte de sortie
 *          pour les cas particuliers (ressource partagée, gestion déléguée).
 *  - Tous les autres : pas de MANAGE, mais VIEW libre (lecture catalogue).
 *
 * La couche (a) est recalculée à la volée à chaque requête → toujours fraîche,
 * jamais périmée. La couche (b) n'est utilisée que pour les exceptions.
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
