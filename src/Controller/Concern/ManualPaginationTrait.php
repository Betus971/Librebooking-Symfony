<?php

namespace App\Controller\Concern;

use Knp\Bundle\PaginatorBundle\Pagination\SlidingPagination;

/**
 * Construction d'une SlidingPagination KnpPaginator à partir d'un tuple
 * `[rows, total]` déjà calculé par un repository (RF-9, étape 9).
 *
 * Mutualise la logique précédemment dupliquée dans AdminReservationController
 * et AdminUserController : permet de partager le rendu DSFR
 * (`templates/_partials/_pagination.html.twig`) sans réécrire la logique de
 * count des repositories (GROUP BY non triviaux, scope par groupe).
 */
trait ManualPaginationTrait
{
    /**
     * @param array<int, mixed>    $rows
     * @param array<string, mixed> $params Filtres à conserver dans les liens de pagination.
     */
    private function buildPagination(
        array $rows,
        int $total,
        int $page,
        int $perPage,
        string $route,
        array $params = []
    ): SlidingPagination {
        $pagination = new SlidingPagination([]);
        $pagination->setItems($rows);
        $pagination->setCurrentPageNumber($page);
        $pagination->setItemNumberPerPage($perPage);
        $pagination->setTotalItemCount($total);
        $pagination->setUsedRoute($route);
        // ⚠️ Construction MANUELLE (sans $paginator->paginate()) : il faut
        // initialiser ces tableaux à [] sinon getPaginationData() fait un
        // array_merge avec null → TypeError.
        $pagination->setPaginatorOptions([]);
        $pagination->setCustomParameters([]);
        // Le template par défaut (knp_paginator.yaml) n'est appliqué qu'au passage
        // par le service paginate(). En manuel, on le pose explicitement.
        $pagination->setTemplate('_partials/_pagination.html.twig');
        foreach ($params as $k => $v) {
            $pagination->setParam($k, $v);
        }

        return $pagination;
    }
}
