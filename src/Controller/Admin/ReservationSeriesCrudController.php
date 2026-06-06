<?php

namespace App\Controller\Admin;

use App\Entity\ReservationSeries;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

class ReservationSeriesCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ReservationSeries::class;
    }
}
