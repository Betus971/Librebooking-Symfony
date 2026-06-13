<?php

namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use Symfony\Component\Translation\TranslatableMessage;

class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular(new TranslatableMessage('admin.user.singular'))
            ->setEntityLabelInPlural(new TranslatableMessage('admin.user.plural'))
            ->setDefaultSort(['lname' => 'ASC', 'fname' => 'ASC'])
            ->setSearchFields(['fname', 'lname', 'email', 'organization'])
            ->setPaginatorPageSize(30);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('language', new TranslatableMessage('entity.user.language'))
                ->setChoices(['Français' => 'fr', 'English' => 'en']))
            ->add(ChoiceFilter::new('timezone', new TranslatableMessage('entity.user.timezone'))
                ->setChoices(['Europe/Paris' => 'Europe/Paris', 'UTC' => 'UTC']));
    }

    public function configureFields(string $pageName): iterable
    {
        if ($pageName === Crud::PAGE_INDEX) {
            yield TextField::new('fname', new TranslatableMessage('entity.user.fname'));
            yield TextField::new('lname', new TranslatableMessage('entity.user.lname'));
            yield EmailField::new('email', new TranslatableMessage('entity.user.email'));
            yield ArrayField::new('roles', new TranslatableMessage('entity.user.roles'));
            yield TextField::new('language', new TranslatableMessage('entity.user.language'));
            yield DateTimeField::new('lastlogin', new TranslatableMessage('entity.user.last_login'))
                ->setFormat('dd/MM/yyyy HH:mm');
            return;
        }

        yield FormField::addTab(new TranslatableMessage('admin.tab.identity'))->setIcon('fa-solid fa-user');

        yield TextField::new('fname', new TranslatableMessage('entity.user.fname'))
            ->setRequired(false);

        yield TextField::new('lname', new TranslatableMessage('entity.user.lname'))
            ->setRequired(false);

        yield EmailField::new('email', new TranslatableMessage('entity.user.email'))
            ->setRequired(true);

        yield TextField::new('organization', new TranslatableMessage('entity.user.organization'))
            ->setRequired(false);

        yield TextField::new('position', new TranslatableMessage('entity.user.position'))
            ->setRequired(false);

        yield TextField::new('phone', new TranslatableMessage('entity.user.phone'))
            ->setRequired(false);

        yield FormField::addTab(new TranslatableMessage('admin.tab.permissions'))->setIcon('fa-solid fa-shield-halved');

        yield ArrayField::new('roles', new TranslatableMessage('entity.user.roles'))
            ->setHelp(new TranslatableMessage('entity.user.roles_help'));

        yield AssociationField::new('resourceGroups', new TranslatableMessage('entity.user.resource_groups'))
            ->setHelp(new TranslatableMessage('entity.user.resource_groups_help'));

        yield FormField::addTab(new TranslatableMessage('admin.tab.preferences'))->setIcon('fa-solid fa-sliders');

        yield ChoiceField::new('language', new TranslatableMessage('entity.user.language'))
            ->setChoices([
                'Français' => 'fr',
                'English'  => 'en',
            ]);

        yield ChoiceField::new('timezone', new TranslatableMessage('entity.user.timezone'))
            ->setChoices([
                'Europe/Paris'  => 'Europe/Paris',
                'Europe/London' => 'Europe/London',
                'UTC'           => 'UTC',
            ]);

        yield FormField::addTab(new TranslatableMessage('admin.tab.activity'))->setIcon('fa-solid fa-clock');

        yield DateTimeField::new('dateCreated', new TranslatableMessage('entity.user.date_created'))
            ->setFormat('dd/MM/yyyy HH:mm')
            ->setDisabled();

        yield DateTimeField::new('lastlogin', new TranslatableMessage('entity.user.last_login'))
            ->setFormat('dd/MM/yyyy HH:mm')
            ->setDisabled();
    }
}
