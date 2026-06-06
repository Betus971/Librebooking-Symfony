<?php

namespace App\Controller\Admin;

use App\Entity\Resource;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ColorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Symfony\Component\Translation\TranslatableMessage;

class ResourceCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Resource::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular(new TranslatableMessage('admin.resource.singular'))
            ->setEntityLabelInPlural(new TranslatableMessage('admin.resource.plural'))
            ->setDefaultSort(['name' => 'ASC'])
            ->setSearchFields(['name', 'location', 'description', 'contactInfo'])
            ->setPaginatorPageSize(25);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(BooleanFilter::new('isActive', new TranslatableMessage('entity.resource.is_active')))
            ->add(BooleanFilter::new('requiresApproval', new TranslatableMessage('entity.resource.requires_approval')))
            ->add(EntityFilter::new('resourceGroup', new TranslatableMessage('entity.resource.group')))
            ->add(EntityFilter::new('category', new TranslatableMessage('entity.resource.category')));
    }

    public function configureFields(string $pageName): iterable
    {
        if ($pageName === Crud::PAGE_INDEX) {
            yield TextField::new('name', new TranslatableMessage('entity.resource.name'));
            yield AssociationField::new('resourceGroup', new TranslatableMessage('entity.resource.group'));
            yield AssociationField::new('category', new TranslatableMessage('entity.resource.category'));
            yield BooleanField::new('requiresApproval', new TranslatableMessage('entity.resource.requires_approval'));
            yield BooleanField::new('isActive', new TranslatableMessage('entity.resource.is_active'));
            return;
        }

        yield FormField::addTab(new TranslatableMessage('admin.tab.general'))->setIcon('fa fa-info-circle');

        yield TextField::new('name', new TranslatableMessage('entity.resource.name'))
            ->setRequired(true)
            ->setHelp(new TranslatableMessage('entity.resource.name_help'));

        yield AssociationField::new('resourceGroup', new TranslatableMessage('entity.resource.group'))
            ->setHelp(new TranslatableMessage('entity.resource.group_help'));

        yield AssociationField::new('category', new TranslatableMessage('entity.resource.category'))
            ->setRequired(false);

        yield AssociationField::new('schedule', new TranslatableMessage('entity.resource.schedule'))
            ->setRequired(true)
            ->setHelp(new TranslatableMessage('entity.resource.schedule_help'));

        yield TextField::new('location', new TranslatableMessage('entity.resource.location'))
            ->setRequired(false);

        yield TextField::new('contactInfo', new TranslatableMessage('entity.resource.contact_info'))
            ->setRequired(false);

        yield TextareaField::new('description', new TranslatableMessage('entity.resource.description'))
            ->setRequired(false)
            ->setNumOfRows(4);

        yield ColorField::new('color', new TranslatableMessage('entity.resource.color'))
            ->setRequired(false);

        yield BooleanField::new('isActive', new TranslatableMessage('entity.resource.is_active'));

        yield FormField::addTab(new TranslatableMessage('admin.tab.booking_rules'))->setIcon('fa fa-sliders-h');

        yield BooleanField::new('requiresApproval', new TranslatableMessage('entity.resource.requires_approval'))
            ->setHelp(new TranslatableMessage('entity.resource.requires_approval_help'));

        yield BooleanField::new('allowMultiday', new TranslatableMessage('entity.resource.allow_multiday'));

        yield IntegerField::new('maxParticipants', new TranslatableMessage('entity.resource.max_participants'))
            ->setRequired(false);

        yield IntegerField::new('minDuration', new TranslatableMessage('entity.resource.min_duration'))
            ->setRequired(false)
            ->setHelp(new TranslatableMessage('entity.resource.duration_help'));

        yield IntegerField::new('maxDuration', new TranslatableMessage('entity.resource.max_duration'))
            ->setRequired(false);

        yield IntegerField::new('bufferTime', new TranslatableMessage('entity.resource.buffer_time'))
            ->setRequired(false)
            ->setHelp(new TranslatableMessage('entity.resource.buffer_time_help'));

        yield IntegerField::new('minNoticeTimeAdd', new TranslatableMessage('entity.resource.min_notice_time_add'))
            ->setRequired(false)
            ->setHelp(new TranslatableMessage('entity.resource.min_notice_time_add_help'));

        yield FormField::addTab(new TranslatableMessage('admin.tab.advanced'))->setIcon('fa fa-cogs');

        yield IntegerField::new('sortOrder', new TranslatableMessage('entity.resource.sort_order'))
            ->setRequired(false)
            ->setHelp(new TranslatableMessage('entity.resource.sort_order_help'));

        yield TextareaField::new('notes', new TranslatableMessage('entity.resource.notes'))
            ->setRequired(false)
            ->setNumOfRows(3)
            ->setHelp(new TranslatableMessage('entity.resource.notes_help'));
    }
}
