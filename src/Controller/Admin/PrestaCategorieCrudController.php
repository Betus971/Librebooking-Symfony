<?php
namespace App\Controller\Admin;

use App\Presta\Entity\PrestaCategorie;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;

class PrestaCategorieCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return PrestaCategorie::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('nom', 'Nom de la catégorie'),
            TextareaField::new('description', 'Description')->hideOnIndex(),
        ];
    }
}
