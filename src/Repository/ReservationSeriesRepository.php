<?php

namespace App\Repository;
use App\Entity\ReservationSeries;
use App\Entity\ReservationStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

class ReservationSeriesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReservationSeries::class);
    }

    /**
     * Applique le SCOPE DE VISIBILITÉ HYBRIDE (P3.4) sur un QueryBuilder.
     *
     * Une série est visible par un gestionnaire si AU MOINS une de ses
     * ressources :
     *   (a) appartient à un de ses ResourceGroup (couche manuelle d'exception).
     *
     * Sémantique des filtres passés par le contrôleur :
     *   - $f['scoped'] absent/false  => super-admin : AUCUNE restriction.
     *   - $f['scoped'] === true      => gestionnaire : on restreint.
     *       $f['resourceGroupIds'] : int[]  (groupes de l'utilisateur)
     *
     * Si le gestionnaire n'a ni groupe, il ne voit rien
     * (sécurité par défaut : `1 = 0`).
     *
     * @param string $resourceAlias alias DQL de la ressource (ex. 'r', 'r2')
     */
    private function applyHybridScope(QueryBuilder $qb, array $f, string $resourceAlias): void
    {
        if (empty($f['scoped'])) {
            return; // Super-admin : pas de filtre.
        }

        $groupIds = (isset($f['resourceGroupIds']) && is_array($f['resourceGroupIds']))
            ? $f['resourceGroupIds']
            : [];

        $conds = [];
        if (!empty($groupIds)) {
            $conds[] = sprintf('IDENTITY(%s.resourceGroup) IN (:scopeGroupIds)', $resourceAlias);
        }

        if ([] === $conds) {
            // Ni groupe connue : ne rien retourner.
            $qb->andWhere('1 = 0');
            return;
        }

        $qb->andWhere('(' . implode(' OR ', $conds) . ')');
        if (!empty($groupIds)) {
            $qb->setParameter('scopeGroupIds', $groupIds);
        }
    }

    public function findPendingWithAgg(int $limit = 50): array
    {
        // PENDING = 1
        return $this->createQueryBuilder('s')
            ->select('s.id AS id, s.title AS title')
            ->addSelect('MIN(i.startDate) AS firstStart, MAX(i.endDate) AS lastEnd')
            ->addSelect('COUNT(DISTINCT rr.resource) AS resCount')
            ->addSelect('MIN(r.name) AS oneName') // si 1 ressource, on affiche son nom
            ->join('s.instances', 'i')
            ->leftJoin('s.reservationResources', 'rr')
            ->leftJoin('rr.resource', 'r')
            ->andWhere('IDENTITY(s.status) = :pendingId')
            ->setParameter('pendingId', ReservationStatus::PENDING)
            ->groupBy('s.id, s.title')
            ->orderBy('firstStart', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    public function findPendingWithFilters(array $f, int $limit, int $offset): array
    {
        $em = $this->getEntityManager();

        $qb = $em->createQueryBuilder()
            ->select([
                's.id AS id',
                's.title AS title',
                's.dateCreated AS createdAt',
                'IDENTITY(s.status) AS statusId',

                // --- LES VRAIS COMPTEURS RESTAURÉS ---
                'COUNT(DISTINCT r.id) AS resCount',
                'MIN(r.name) AS oneName',
                // -------------------------------------

                'MIN(i.startDate) AS firstStart',
                'MAX(i.endDate) AS lastEnd',
                'CONCAT(COALESCE(u.fname, \'\'), \' \', COALESCE(u.lname, \'\')) AS ownerName',
                '0 AS requiresApproval',

                // --- COMPTEUR PIÈCES JOINTES ---
                'COUNT(DISTINCT att.id) AS attachmentCount',
            ])
            ->from(ReservationSeries::class, 's')
            ->leftJoin('App\Entity\ReservationInstance', 'i', 'WITH', 'i.series = s')
            // --- LES VRAIES JOINTURES RESTAURÉES ---
            ->leftJoin('App\Entity\ReservationResource', 'rr', 'WITH', 'rr.series = s')
            ->leftJoin('rr.resource', 'r')
            // ---------------------------------------
            ->leftJoin('s.reservationAttachments', 'att')
            ->leftJoin('s.owner', 'u')
            ->andWhere('IDENTITY(s.status) = :pending')
            ->setParameter('pending', ReservationStatus::PENDING);

        // --- FILTRES ---
        if (!empty($f['q'])) {
            $qb->andWhere('LOWER(s.title) LIKE :q OR LOWER(u.fname) LIKE :q OR LOWER(u.lname) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower($f['q']).'%');
        }
        if (!empty($f['from'])) {
            $qb->andWhere('i.endDate >= :from')->setParameter('from', $f['from']);
        }
        if (!empty($f['to'])) {
            $qb->andWhere('i.startDate <= :to')->setParameter('to', $f['to']);
        }

        // --- FILTRES SUR LES RESSOURCES RESTAURÉS ---
        if (!empty($f['resource'])) {
            $qb->andWhere('r.id = :rid')->setParameter('rid', $f['resource']);
        }
        if (($f['approval'] ?? 'all') === 'req') {
            $qb->andWhere('EXISTS (SELECT 1 FROM App\Entity\ReservationResource rr3 JOIN rr3.resource r3 WITH r3.requiresApproval = true WHERE rr3.series = s)');
        }
        if (($f['approval'] ?? 'all') === 'noreq') {
            $qb->andWhere('NOT EXISTS (SELECT 1 FROM App\Entity\ReservationResource rr3 JOIN rr3.resource r3 WITH r3.requiresApproval = true WHERE rr3.series = s)');
        }

        // --- SCOPE DE VISIBILITÉ HYBRIDE (groupes OU code unité) ---
        // Les ressources (alias r) sont déjà jointes ci-dessus.
        $this->applyHybridScope($qb, $f, 'r');

        $qb->groupBy('s.id')
            ->addGroupBy('u.fname')
            ->addGroupBy('u.lname')
            ->orderBy('s.dateCreated', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        $rows = $qb->getQuery()->getArrayResult();

        // --- REQUÊTE POUR LE TOTAL (Pagination) ---
        $countQb = $em->createQueryBuilder()
            ->select('COUNT(DISTINCT s2.id)')
            ->from(ReservationSeries::class, 's2')
            ->leftJoin('App\Entity\ReservationInstance', 'i2', 'WITH', 'i2.series = s2')
            ->leftJoin('App\Entity\ReservationResource', 'rr2', 'WITH', 'rr2.series = s2')
            ->leftJoin('rr2.resource', 'r2')
            ->leftJoin('s2.owner', 'u2')
            ->andWhere('IDENTITY(s2.status) = :pending')
            ->setParameter('pending', ReservationStatus::PENDING);

        if (!empty($f['q'])) {
            $countQb->andWhere('LOWER(s2.title) LIKE :q OR LOWER(u2.fname) LIKE :q OR LOWER(u2.lname) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower($f['q']).'%');
        }
        if (!empty($f['resource'])) {
            $countQb->andWhere('r2.id = :rid')->setParameter('rid', $f['resource']);
        }
        if (!empty($f['from'])) {
            $countQb->andWhere('i2.endDate >= :from')->setParameter('from', $f['from']);
        }
        if (!empty($f['to'])) {
            $countQb->andWhere('i2.startDate <= :to')->setParameter('to', $f['to']);
        }
        if (($f['approval'] ?? 'all') === 'req')   { $countQb->andWhere('EXISTS (SELECT 1 FROM App\Entity\ReservationResource rr3 JOIN rr3.resource r3 WITH r3.requiresApproval = true WHERE rr3.series = s2)'); }
        if (($f['approval'] ?? 'all') === 'noreq') { $countQb->andWhere('NOT EXISTS (SELECT 1 FROM App\Entity\ReservationResource rr3 JOIN rr3.resource r3 WITH r3.requiresApproval = true WHERE rr3.series = s2)'); }

        // Même scope hybride sur le count, pour que la pagination reflète la réalité.
        // (les ressources r2 sont déjà jointes ci-dessus)
        $this->applyHybridScope($countQb, $f, 'r2');

        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        return [$rows, $total];
    }

    public function findAllWithFilters(array $f, int $limit, int $offset): array
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder()
            ->select([
                's.id AS id',
                's.uuid AS uuid',

                's.title AS title',
                's.dateCreated AS createdAt',
                'IDENTITY(s.status) AS statusId',
                'COUNT(DISTINCT r.id) AS resCount',
                'MIN(r.name) AS oneName',
                'MIN(i.startDate) AS firstStart',
                'MAX(i.endDate) AS lastEnd',
                'CONCAT(COALESCE(u.fname, \'\'), \' \', COALESCE(u.lname, \'\')) AS ownerName',

                // --- LIGNE SUPPRIMÉE ICI (s.status AS statusObj) ---
                // On a déjà IDENTITY(s.status) AS statusId, c'est suffisant.
            ])
            ->from(ReservationSeries::class, 's')
            ->leftJoin('App\Entity\ReservationInstance', 'i', 'WITH', 'i.series = s')
            ->leftJoin('App\Entity\ReservationResource', 'rr', 'WITH', 'rr.series = s')
            ->leftJoin('rr.resource', 'r')
            ->leftJoin('s.owner', 'u');

        // --- FILTRES ---
        if (!empty($f['q'])) {
            $qb->andWhere('LOWER(s.title) LIKE :q OR LOWER(u.fname) LIKE :q OR LOWER(u.lname) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower($f['q']).'%');
        }

        // Scope hybride (groupes OU code unité) — cf. findPendingWithFilters.
        // Les ressources (alias r) sont déjà jointes ci-dessus.
        $this->applyHybridScope($qb, $f, 'r');

        $qb->groupBy('s.id')
            ->addGroupBy('s.uuid')
            ->addGroupBy('s.title')
            ->addGroupBy('s.dateCreated')
            ->addGroupBy('u.fname')
            ->addGroupBy('u.lname')
            ->orderBy('s.dateCreated', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        $rows = $qb->getQuery()->getArrayResult();

        // --- COMPTE TOTAL ---
        $countQb = $em->createQueryBuilder()
            ->select('COUNT(DISTINCT s2.id)')
            ->from(ReservationSeries::class, 's2')
            ->leftJoin('s2.owner', 'u2');

        if (!empty($f['q'])) {
            $countQb->andWhere('LOWER(s2.title) LIKE :q OR LOWER(u2.fname) LIKE :q OR LOWER(u2.lname) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower($f['q']).'%');
        }

        // Scope hybride : on doit rejoindre les ressources dans le count pour filtrer
        // (jointure ajoutée dès que le scope s'applique, pas seulement pour les groupes).
        if (!empty($f['scoped'])) {
            $countQb->leftJoin('App\Entity\ReservationResource', 'rr2', 'WITH', 'rr2.series = s2')
                ->leftJoin('rr2.resource', 'r2');
            $this->applyHybridScope($countQb, $f, 'r2');
        }

        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        return [$rows, $total];
    }
    /**
     * Vrai si au moins une ressource de la série exige une approbation.
     *
     * Encapsule la règle utilisée par {@see \App\Domain\Reservation\ReservationWorkflow}.
     */
    public function requiresApproval(ReservationSeries $series): bool
    {
        $count = (int) $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from('App\Entity\ReservationResource', 'rr')
            ->join('rr.resource', 'r')
            ->where('rr.series = :series')
            ->andWhere('r.requiresApproval = true')
            ->setParameter('series', $series)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function findNextForUser($user): ?ReservationSeries
    {
        return $this->createQueryBuilder('s')
            ->join('s.instances', 'i') // On joint les créneaux (instances)
            ->andWhere('s.owner = :user')
            ->andWhere('i.startDate > :now') // Qui n'est pas encore passée
            ->andWhere('IDENTITY(s.status) != :cancelled')
            ->setParameter('cancelled', ReservationStatus::CANCELLED)
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTime())
            ->orderBy('i.startDate', 'ASC') // La plus proche en premier
            ->setMaxResults(1) // Une seule
            ->getQuery()
            ->getOneOrNullResult();
    }
}

