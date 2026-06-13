<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Recherche paginée d'utilisateurs par email, nom ou uid.
     *
     * @return array{users: User[], total: int}
     */
    public function searchPaginated(string $q = '', int $page = 1, int $limit = 25): array
    {
        $qb = $this->createQueryBuilder('u')
            ->orderBy('u.email', 'ASC');

        if ($q !== '') {
            $qb->andWhere(
                'LOWER(u.email) LIKE :q OR LOWER(u.lname) LIKE :q OR LOWER(u.uid) LIKE :q'
            )->setParameter('q', '%' . strtolower($q) . '%');
        }

        $total = (clone $qb)->select('COUNT(u.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

        $users = $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['users' => $users, 'total' => (int) $total];
    }
}
