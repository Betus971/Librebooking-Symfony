<?php

namespace App\Repository;

use App\Entity\Layout;
use App\Entity\TimeBlock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

  final class TimeBlockRepository extends ServiceEntityRepository

  {
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TimeBlock::class);
    }

    /** Supprime les créneaux d’un layout pour certains jours (0..6). */
      public function deleteByLayoutAndDays(Layout $layout, array $days): int
      {
          if (empty($days)) {
              return 0; // éviter IN ()
          }

          return $this->getEntityManager()->createQueryBuilder()
              ->delete(TimeBlock::class, 'tb')
              ->where('tb.layout = :layout')
              ->andWhere('tb.dayOfWeek IN (:days)')
              ->setParameter('layout', $layout)
              ->setParameter('days', $days)
              ->getQuery()
              ->execute(); // nb de lignes supprimées
      }

      /** Vide toute la semaine + “Tous les jours” (NULL). */
      public function deleteAllWeekForLayout(Layout $layout): int
      {
          return $this->getEntityManager()->createQueryBuilder()
              ->delete(TimeBlock::class, 'tb')
              ->where('tb.layout = :layout')
              ->andWhere('tb.dayOfWeek IN (:days) OR tb.dayOfWeek IS NULL')
              ->setParameter('layout', $layout)
              ->setParameter('days', [0,1,2,3,4,5,6])
              ->getQuery()
              ->execute();
      }

      /**
       * Créneaux OUVERTS d'un layout, sous forme de lignes brutes :
       *   [ ['dow' => int|null, 's' => time, 'e' => time], ... ]
       *
       * Encapsule la requête utilisée par {@see \App\Service\AvailabilityChecker::isFree()}.
       */
      public function findOpenBlocksByLayout(Layout $layout): array
      {
          return $this->createQueryBuilder('tb')
              ->select('tb.dayOfWeek AS dow, tb.startTime AS s, tb.endTime AS e')
              ->where('tb.layout = :layout')
              ->andWhere('tb.availabilityCode = :open')
              ->setParameter('open', TimeBlock::OPEN)
              ->setParameter('layout', $layout)
              ->getQuery()
              ->getArrayResult();
      }

  }
