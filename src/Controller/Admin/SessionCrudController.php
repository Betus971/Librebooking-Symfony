<?php
namespace App\Controller\Admin;

use App\Presta\Entity\Session;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class SessionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Session::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            AssociationField::new('prestataire', 'Prestataire'),
            AssociationField::new('service', 'Service'),
            DateTimeField::new('dateDebut', 'Début'),
            DateTimeField::new('dateFin', 'Fin'),
            IntegerField::new('nbInscrits', 'Nombre d\'inscrits')->hideOnForm(),
            TextField::new('clientNom', 'Client (Imprévu)')->hideOnIndex(),
            TextField::new('note', 'Note')->hideOnIndex(),
        ];
    }
}
