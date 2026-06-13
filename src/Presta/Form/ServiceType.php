<?php

namespace App\Presta\Form;

use App\Presta\Entity\Service;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ServiceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('libelle', TextType::class, [
                'label' => 'Nom de la prestation (ex: Coupe Homme, Séance CrossFit)',
                'attr' => ['class' => 'fr-input']
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description détaillée',
                'required' => false,
                'attr' => ['class' => 'fr-input', 'rows' => 3]
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type de prestation',
                'choices' => [
                    'Individuel (Le client réserve un créneau dans vos horaires)' => Service::TYPE_INDIVIDUEL,
                    'Groupe (Vous créez des séances à horaires fixes)' => Service::TYPE_GROUPE,
                ],
                'attr' => ['class' => 'fr-select']
            ])
            ->add('dureeMinutes', IntegerType::class, [
                'label' => 'Durée (en minutes)',
                'attr' => ['class' => 'fr-input', 'min' => 5]
            ])
            ->add('capaciteMax', IntegerType::class, [
                'label' => 'Capacité maximale (Nombre de personnes)',
                'attr' => ['class' => 'fr-input', 'min' => 1]
            ])
            ->add('isActive', \Symfony\Component\Form\Extension\Core\Type\CheckboxType::class, [
                'label' => 'Prestation active (visible pour les clients)',
                'required' => false,
                'attr' => ['class' => 'fr-toggle__input']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Service::class,
        ]);
    }
}
