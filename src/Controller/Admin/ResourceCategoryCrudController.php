<?php

namespace App\Controller\Admin;

use App\Entity\ResourceCategory;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

class ResourceCategoryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ResourceCategory::class;
    }
}
