<?php

namespace App\Controller\Admin;

use App\Entity\ResourceGroup;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

class ResourceGroupCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ResourceGroup::class;
    }
}
