<?php
namespace App\Controller\Admin;

use App\Presta\Entity\Service;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ColorField;

class ServiceCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Service::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            AssociationField::new('prestataire', 'Prestataire'),
            AssociationField::new('categorie', 'Catégorie'),
            TextField::new('libelle', 'Libellé'),
            TextareaField::new('description', 'Description')->hideOnIndex(),
            IntegerField::new('dureeMinutes', 'Durée (min)'),
            ChoiceField::new('type', 'Type')->setChoices([
                'Individuel' => Service::TYPE_INDIVIDUEL,
                'Groupe' => Service::TYPE_GROUPE,
            ]),
            IntegerField::new('capaciteMax', 'Capacité Max')->hideOnIndex(),
            BooleanField::new('isActive', 'Actif'),
            BooleanField::new('requiresApproval', 'Approbation Requise')->hideOnIndex(),
            ColorField::new('couleur', 'Couleur')->hideOnIndex(),
        ];
    }
}
