<?php
namespace App\Controller\Admin;

use App\Presta\Entity\Prestataire;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;

class PrestataireCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Prestataire::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            AssociationField::new('user', 'Utilisateur (Compte)'),
            TextField::new('nom', 'Nom'),
            TextField::new('prenom', 'Prénom'),
            TextField::new('description', 'Description')->hideOnIndex(),
            TextField::new('photo', 'Photo (URL)')->hideOnIndex(),
            BooleanField::new('isActive', 'Actif'),
            IntegerField::new('horizonJours', 'Horizon (Jours)')->hideOnIndex(),
            IntegerField::new('delaiAnnulationHeures', 'Délai d\'annulation (Heures)')->hideOnIndex(),
            BooleanField::new('unRdvActifParClient', 'Limiter à 1 RDV par client')->hideOnIndex(),
        ];
    }
}
