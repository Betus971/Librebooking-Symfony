<?php

namespace App\Form;

use App\Entity\Equipement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EquipementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom de l\'équipement',
                'help'  => 'Ex. : Wifi, Vidéoprojecteur, Tableau blanc. Le nom doit être unique.',
                'attr'  => ['class' => 'fr-input', 'placeholder' => 'Ex. : Vidéoprojecteur'],
            ])
            ->add('actif', CheckboxType::class, [
                'label'    => 'Actif (proposé au choix sur les ressources)',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Equipement::class,
        ]);
    }
}
