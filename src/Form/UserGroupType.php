<?php

namespace App\Form;

use App\Entity\ResourceGroup;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserGroupType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('resourceGroups', EntityType::class, [
                'class' => ResourceGroup::class,
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => true, // true = cases à cocher (checkboxes)
                'label' => 'Équipes / Groupes assignés à cet utilisateur',
                'attr' => ['class' => 'fr-checkbox-group'], // Style optionnel si tu utilises le DSFR
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
