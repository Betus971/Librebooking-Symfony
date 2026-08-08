<?php
namespace App\Controller\Admin;

use App\Presta\Entity\PrestaAbsence;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class PrestaAbsenceCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return PrestaAbsence::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            AssociationField::new('prestataire', 'Prestataire'),
            DateTimeField::new('dateDebut', 'Début'),
            DateTimeField::new('dateFin', 'Fin'),
            TextField::new('motif', 'Motif'),
        ];
    }
}
