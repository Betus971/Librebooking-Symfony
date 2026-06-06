<?php

namespace App\Form;

use App\Entity\Resource;
use App\Entity\ResourceGroup;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ResourceGroupType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de l\'équipe (ex: Logistique, Amphi...)',
                'attr' => ['class' => 'fr-input']
            ])

            ->add('resources', EntityType::class, [
                'class' => Resource::class,
                'choice_label' => 'name', // Affichera le nom de la salle
                'multiple' => true,       // On peut en choisir plusieurs
                'expanded' => true,       // Affiche des cases à cocher (plus visuel)
                'required' => false,      // Ce n'est pas obligatoire d'affecter des salles tout de suite
                'label' => 'Salles / Véhicules gérés par cette équipe',
                'by_reference' => false,  // ⚠️ TRÈS IMPORTANT : Force Doctrine à utiliser addResource() pour bien lier la salle !
                'attr' => ['class' => 'fr-checkbox-group fr-mt-3w']
            ])
        ;
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ResourceGroup::class,
        ]);
    }
}
