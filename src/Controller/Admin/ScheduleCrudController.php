<?php

namespace App\Controller\Admin;

use App\Entity\Schedule;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Translation\TranslatableMessage;

class ScheduleCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Schedule::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular(new TranslatableMessage('admin.schedule.singular'))
            ->setEntityLabelInPlural(new TranslatableMessage('admin.schedule.plural'))
            ->setDefaultSort(['name' => 'ASC'])
            ->setSearchFields(['name'])
            ->setPaginatorPageSize(25)
            ->setHelp('index', new TranslatableMessage('admin.schedule.help'));
    }

    public function configureFields(string $pageName): iterable
    {
        if ($pageName === Crud::PAGE_INDEX) {
            yield TextField::new('name', new TranslatableMessage('entity.schedule.name'));
            yield BooleanField::new('isDefault', new TranslatableMessage('entity.schedule.is_default'));
            yield DateTimeField::new('startDate', new TranslatableMessage('entity.schedule.start_date'))
                ->setFormat('dd/MM/yyyy');
            yield DateTimeField::new('endDate', new TranslatableMessage('entity.schedule.end_date'))
                ->setFormat('dd/MM/yyyy');
            return;
        }

        yield FormField::addTab(new TranslatableMessage('admin.tab.general'))->setIcon('fa fa-calendar');

        yield TextField::new('name', new TranslatableMessage('entity.schedule.name'))
            ->setRequired(true);

        yield BooleanField::new('isDefault', new TranslatableMessage('entity.schedule.is_default'))
            ->setHelp(new TranslatableMessage('entity.schedule.is_default_help'));

        yield AssociationField::new('layout', new TranslatableMessage('entity.schedule.layout'))
            ->setRequired(false)
            ->setHelp(new TranslatableMessage('entity.schedule.layout_help'));

        yield FormField::addTab(new TranslatableMessage('admin.tab.availability'))->setIcon('fa fa-clock');

        yield ChoiceField::new('weekdayStart', new TranslatableMessage('entity.schedule.weekday_start'))
            ->setChoices([
                'Lundi'    => 1,
                'Mardi'    => 2,
                'Mercredi' => 3,
                'Jeudi'    => 4,
                'Vendredi' => 5,
                'Samedi'   => 6,
                'Dimanche' => 0,
            ]);

        yield IntegerField::new('daysVisible', new TranslatableMessage('entity.schedule.days_visible'))
            ->setHelp(new TranslatableMessage('entity.schedule.days_visible_help'));

        yield DateTimeField::new('startDate', new TranslatableMessage('entity.schedule.start_date'))
            ->setRequired(false)
            ->setHelp(new TranslatableMessage('entity.schedule.start_date_help'));

        yield DateTimeField::new('endDate', new TranslatableMessage('entity.schedule.end_date'))
            ->setRequired(false)
            ->setHelp(new TranslatableMessage('entity.schedule.end_date_help'));

        yield FormField::addTab(new TranslatableMessage('admin.tab.advanced'))->setIcon('fa fa-cogs');

        yield BooleanField::new('published', new TranslatableMessage('entity.schedule.published'));

        yield BooleanField::new('allowConcurrentBookings', new TranslatableMessage('entity.schedule.allow_concurrent'))
            ->setHelp(new TranslatableMessage('entity.schedule.allow_concurrent_help'));

        yield IntegerField::new('totalConcurrentReservations', new TranslatableMessage('entity.schedule.total_concurrent'))
            ->setRequired(false);

        yield IntegerField::new('maxResourcesPerReservation', new TranslatableMessage('entity.schedule.max_resources'))
            ->setRequired(false);

        yield TextareaField::new('notes', new TranslatableMessage('entity.schedule.notes'))
            ->setRequired(false)
            ->setNumOfRows(3);
    }
}
