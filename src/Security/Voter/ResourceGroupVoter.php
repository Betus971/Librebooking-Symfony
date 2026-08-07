<?php

namespace App\Security\Voter;

use App\Entity\ResourceGroup;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Autorisation de GESTION d'un groupe de ressources (équipe).
 *
 * MANAGE est accordé si :
 *  - l'utilisateur est ROLE_SUPER_ADMIN (gère tous les groupes) ; OU
 *  - l'utilisateur est un ADMINISTRATEUR DÉSIGNÉ de ce groupe précis
 *    (relation ResourceGroup.admins) — délégation manuelle, façon LibreBooking.
 *
 * La création et la suppression de groupes restent réservées au super-admin
 * (gérées par #[IsGranted('ROLE_SUPER_ADMIN')] sur les actions concernées).
 */
final class ResourceGroupVoter extends Voter
{
    public const MANAGE = 'MANAGE_GROUP';

    public function __construct(private readonly Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::MANAGE && $subject instanceof ResourceGroup;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if ($this->security->isGranted('ROLE_SUPER_ADMIN')) {
            return true;
        }

        /** @var ResourceGroup $group */
        $group = $subject;

        return $group->isAdministeredBy($user);
    }
}
