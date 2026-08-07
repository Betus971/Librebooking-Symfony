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
     * Recherche paginée d'utilisateurs par email, nom, uid ou nigend.
     *
     * @return array{users: User[], total: int}
     */
    public function searchPaginated(string $q = '', int $page = 1, int $limit = 25): array
    {
        $qb = $this->createQueryBuilder('u')
            ->orderBy('u.email', 'ASC');

        if ($q !== '') {
            $qb->andWhere(
                'LOWER(u.email) LIKE :q OR LOWER(u.lname) LIKE :q OR LOWER(u.uid) LIKE :q OR LOWER(u.nigend) LIKE :q'
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

    /**
     * Recherche d'utilisateurs pour l'auto-complétion (champ participant).
     * Cherche sur prénom, nom et email parmi les comptes locaux (provisionnés
     * par le SSO à la première connexion).
     *
     * @return list<array{email: string, label: string}>
     */
    public function searchForAutocomplete(string $q, int $limit = 10): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }

        $rows = $this->createQueryBuilder('u')
            ->select('u.id AS id', 'u.email AS email', 'u.fname AS fname', 'u.lname AS lname')
            ->where('u.email IS NOT NULL')
            ->andWhere('LOWER(u.email) LIKE :q OR LOWER(u.fname) LIKE :q OR LOWER(u.lname) LIKE :q')
            ->setParameter('q', '%' . mb_strtolower($q) . '%')
            ->orderBy('u.lname', 'ASC')
            ->addOrderBy('u.fname', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(static function (array $r): array {
            $name = trim(($r['fname'] ?? '') . ' ' . ($r['lname'] ?? ''));
            return [
                'id' => (int) $r['id'],
                'email' => (string) $r['email'],
                'label' => $name !== '' ? $name . ' — ' . $r['email'] : (string) $r['email'],
            ];
        }, $rows);
    }
}
