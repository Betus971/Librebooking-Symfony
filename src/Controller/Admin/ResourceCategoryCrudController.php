<?php

namespace App\Controller\Admin;

use App\Entity\ResourceCategory;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Translation\TranslatableMessage;

class ResourceCategoryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ResourceCategory::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular(new TranslatableMessage('admin.resource_category.singular'))
            ->setEntityLabelInPlural(new TranslatableMessage('admin.resource_category.plural'))
            ->setDefaultSort(['name' => 'ASC'])
            ->setSearchFields(['name', 'description'])
            ->setPaginatorPageSize(25);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', new TranslatableMessage('entity.resource_category.name'))
            ->setRequired(false);

        yield TextareaField::new('description', new TranslatableMessage('entity.resource_category.description'))
            ->setRequired(false)
            ->setNumOfRows(3);
    }
}
