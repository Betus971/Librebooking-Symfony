<?php

namespace App\Controller\Admin;

use App\Entity\Announcement;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use Symfony\Component\Translation\TranslatableMessage;

class AnnouncementCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Announcement::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular(new TranslatableMessage('admin.announcement.singular'))
            ->setEntityLabelInPlural(new TranslatableMessage('admin.announcement.plural'))
            ->setDefaultSort(['endDate' => 'DESC'])
            ->setSearchFields(['message'])
            ->setPaginatorPageSize(25);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextareaField::new('message', new TranslatableMessage('entity.announcement.message'))
            ->setRequired(true)
            ->setNumOfRows(4)
            ->setHelp(new TranslatableMessage('entity.announcement.message_help'));

        yield DateTimeField::new('startDate', new TranslatableMessage('entity.announcement.start_date'))
            ->setRequired(false)
            ->setFormat('dd/MM/yyyy')
            ->setHelp(new TranslatableMessage('entity.announcement.start_date_help'));

        yield DateTimeField::new('endDate', new TranslatableMessage('entity.announcement.end_date'))
            ->setRequired(true)
            ->setFormat('dd/MM/yyyy')
            ->setHelp(new TranslatableMessage('entity.announcement.end_date_help'));

        yield IntegerField::new('priority', new TranslatableMessage('entity.announcement.priority'))
            ->setHelp(new TranslatableMessage('entity.announcement.priority_help'));
    }
}
