<?php

namespace App\Controller\Admin;

use App\Entity\ResourceGroup;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Translation\TranslatableMessage;

class ResourceGroupCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ResourceGroup::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular(new TranslatableMessage('admin.resource_group.singular'))
            ->setEntityLabelInPlural(new TranslatableMessage('admin.resource_group.plural'))
            ->setDefaultSort(['name' => 'ASC'])
            ->setSearchFields(['name'])
            ->setPaginatorPageSize(25)
            ->setHelp('index', new TranslatableMessage('admin.resource_group.help'));
    }

    public function configureFields(string $pageName): iterable
    {
        if ($pageName === Crud::PAGE_INDEX) {
            yield TextField::new('name', new TranslatableMessage('entity.resource_group.name'));
            yield AssociationField::new('users', new TranslatableMessage('entity.resource_group.managers'));
            return;
        }

        yield FormField::addTab(new TranslatableMessage('admin.tab.general'))->setIcon('fa-solid fa-users');

        yield TextField::new('name', new TranslatableMessage('entity.resource_group.name'))
            ->setRequired(false)
            ->setHelp(new TranslatableMessage('entity.resource_group.name_help'));

        yield AssociationField::new('users', new TranslatableMessage('entity.resource_group.managers'))
            ->setHelp(new TranslatableMessage('entity.resource_group.managers_help'))
            ->setRequired(false);
    }
}
