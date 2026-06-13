<?php

namespace App\Controller\Admin;

use App\Entity\Layout;
use App\Form\TimeBlockAdminType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Translation\TranslatableMessage;

class LayoutCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Layout::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular(new TranslatableMessage('admin.layout.singular'))
            ->setEntityLabelInPlural(new TranslatableMessage('admin.layout.plural'))
            ->setDefaultSort(['name' => 'ASC'])
            ->setSearchFields(['name'])
            ->setPaginatorPageSize(25)
            ->setHelp('index', new TranslatableMessage('admin.layout.help'))
            ->setHelp('new', new TranslatableMessage('admin.layout.help'))
            ->setHelp('edit', new TranslatableMessage('admin.layout.help'));
    }

    public function configureFields(string $pageName): iterable
    {
        if ($pageName === Crud::PAGE_INDEX) {
            yield TextField::new('name', new TranslatableMessage('entity.layout.name'));
            yield TextField::new('timezone', new TranslatableMessage('entity.layout.timezone'));
            yield IntegerField::new('timeBlocks', new TranslatableMessage('entity.layout.blocks'))
                ->formatValue(static fn ($value, Layout $entity) => $entity->getTimeBlocks()->count());
            return;
        }

        yield FormField::addTab(new TranslatableMessage('admin.tab.general'))->setIcon('fa-solid fa-table-cells');

        yield TextField::new('name', new TranslatableMessage('entity.layout.name'));

        yield TextField::new('timezone', new TranslatableMessage('entity.layout.timezone'))
            ->setHelp(new TranslatableMessage('entity.layout.timezone_help'));

        yield FormField::addTab(new TranslatableMessage('entity.layout.blocks'))->setIcon('fa-solid fa-clock');

        yield CollectionField::new('timeBlocks', new TranslatableMessage('entity.layout.blocks'))
            ->setEntryType(TimeBlockAdminType::class)
            ->setEntryIsComplex(true)
            ->allowAdd()
            ->allowDelete()
            ->renderExpanded()
            // Force l'appel de addTimeBlock()/removeTimeBlock() (qui fixe le layout)
            // au lieu d'une modification de la collection par référence.
            ->setFormTypeOption('by_reference', false)
            ->setHelp(new TranslatableMessage('entity.layout.blocks_help'));
    }
}
