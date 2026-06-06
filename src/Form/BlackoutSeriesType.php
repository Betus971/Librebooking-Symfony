<?php

namespace App\Form;

use App\Entity\BlackoutSeries;
use App\Entity\Resource;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BlackoutSeriesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', null, [
                'label' => 'Titre de la fermeture',
                'attr' => ['placeholder' => 'ex: Travaux Peinture']
            ])
            ->add('resource', EntityType::class, [
                'class' => Resource::class,
                'choice_label' => 'name',
                'label' => 'Ressource concernée',
                'placeholder' => 'Choisir une salle...',
            ])
            ->add('description', null, [
                'label' => 'Note interne (Optionnel)',
                'required' => false
            ])

            // 👇 CHAMPS VIRTUELS (Servent à créer l'Instance) 👇
            ->add('start', DateTimeType::class, [
                'label' => 'Date de début',
                'widget' => 'single_text',
                'mapped' => false, // ⚠️ IMPORTANT : Ce champ n'est pas dans l'entité Série
                'required' => true,
            ])
            ->add('end', DateTimeType::class, [
                'label' => 'Date de fin',
                'widget' => 'single_text',
                'mapped' => false, // ⚠️ IMPORTANT
                'required' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BlackoutSeries::class,
        ]);
    }
}
