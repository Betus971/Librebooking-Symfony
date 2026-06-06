<?php

namespace App\Security\Voter;

use App\Entity\ReservationSeries;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Autorisation "scopée" sur une série de réservations.
 *
 * MANAGE (approve / reject / cancel / edit admin) :
 *   - ROLE_SUPER_ADMIN : toujours.
 *   - ROLE_ADMIN_RESSOURCE : si au moins une ressource de la série appartient
 *     à un ResourceGroup de l'utilisateur.
 *
 * Règle métier confirmée par l'utilisateur : une série porte sur des ressources
 * d'un seul et même groupe (pas de mix amphi + véhicule). Le "au moins une"
 * revient donc à "toutes".
 *
 * VIEW_DETAILS (titre, description, owner, pièces jointes) :
 *   - Owner de la série.
 *   - Super-admin ou gestionnaire du groupe (via MANAGE).
 *   - TODO futur : participants (quand la notion sera matérialisée côté entité).
 */
final class ReservationSeriesVoter extends Voter
{
    public const MANAGE       = 'MANAGE';
    public const VIEW_DETAILS = 'VIEW_DETAILS';

    public function __construct(private readonly Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::MANAGE, self::VIEW_DETAILS], true)
            && $subject instanceof ReservationSeries;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $vote ??= new Vote();
        /** @var ReservationSeries $series */
        $series = $subject;

        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::MANAGE       => $this->canManage($series, $user),
            self::VIEW_DETAILS => $this->canViewDetails($series, $user),
            default            => false,
        };
    }

    private function canManage(ReservationSeries $series, User $user): bool
    {
        if ($this->security->isGranted('ROLE_SUPER_ADMIN')) {
            return true;
        }

        if (!$this->security->isGranted('ROLE_ADMIN_RESSOURCE')) {
            return false;
        }

        // Groupes de l'utilisateur : ids à matcher.
        $userGroupIds = [];
        foreach ($user->getResourceGroups() as $group) {
            if (null !== $group->getId()) {
                $userGroupIds[$group->getId()] = true;
            }
        }

        if ([] === $userGroupIds) {
            return false;
        }

        // On cherche AU MOINS une ressource de la série dans un des groupes de l'utilisateur.
        foreach ($series->getReservationResources() as $rr) {
            $resource = $rr->getResource();
            if (null === $resource) {
                continue;
            }
            $group = $resource->getResourceGroup();
            if (null !== $group && isset($userGroupIds[$group->getId()])) {
                return true;
            }
        }

        return false;
    }

    private function canViewDetails(ReservationSeries $series, User $user): bool
    {
        // Owner : accès à sa propre réservation.
        if ($series->getOwner() && $series->getOwner()->getId() === $user->getId()) {
            return true;
        }

        // Gestionnaire ou super-admin : via MANAGE.
        return $this->canManage($series, $user);
    }
}
