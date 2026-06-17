<?php

namespace App\Form;

use App\Entity\TimeBlock;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TimeBlockType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $tz = $options['layout_timezone'];

        $builder
                  ->add('days', ChoiceType::class, [
                  'mapped'     => false,
                  'label'      => 'Jours concernés',
                  'expanded'   => true,   // checkboxes
                  'multiple'   => true,
                  'required'   => false,
                  'choices'    => [
                      'Lundi' => 1, 'Mardi' => 2, 'Mercredi' => 3, 'Jeudi' => 4, 'Vendredi' => 5,
                      'Samedi' => 6, 'Dimanche' => 0,
                  ],
                  // Pas de valeur par défaut ici : le contrôleur gère le fallback (semaine entière)
                  'label_attr' => ['class' => 'fr-label'],
              ]);


        // Option unitaire (un seul bloc) — mappé sur l’entité
        $builder->add('dayOfWeek', ChoiceType::class, [
            'label'       => 'Jour (mode unitaire)',
            'required'    => false,
            'placeholder' => '— (laisse vide si tu utilises “Jours concernés”) —',
            'choices'     => [
                'Lundi' => 1, 'Mardi' => 2, 'Mercredi' => 3, 'Jeudi' => 4, 'Vendredi' => 5,
                'Samedi' => 6, 'Dimanche' => 0,
            ],
        ]);

        $builder
            ->add('startTime', TimeType::class, [
                'label' => 'Heure de début',
                'widget' => 'single_text',
                'with_seconds' => false,
                'mapped' => false,
                'input' => 'datetime_immutable',
                'model_timezone' => $tz,
                'view_timezone'  => $tz,
                'attr' => ['class' => 'fr-input', 'step' => 60], // pas de 1 min
                'label_attr' => ['class' => 'fr-label'],
            ])

            ->add('endTime', TimeType::class, [
                'label' => 'Heure de fin',
                'widget' => 'single_text',
                'with_seconds' => false,
                'mapped' => false,
                'input' => 'datetime_immutable',
                'model_timezone' => $tz,
                'view_timezone'  => $tz,
                'attr' => ['class' => 'fr-input', 'step' => 60],
                'label_attr' => ['class' => 'fr-label'],
            ])
            ->add('slotDuration', IntegerType::class, [
                'label'   => 'Durée des créneaux (minutes)',
                'mapped'  => false,
                'required' => false,
                'attr'    => ['class' => 'fr-input', 'placeholder' => 'Ex: 30', 'min' => 1, 'step' => 1],
            ])
            ->add('availabilityCode', ChoiceType::class, [
                'label' => 'Disponibilité',
                'choices' => ['Disponible' => 1, 'Fermé' => 0],
                'expanded' => true,
                'label_attr' => ['class' => 'fr-label'],
                'choice_attr' => fn () => ['class' => 'fr-radio-group'],
            ])

            ->add('label', TextType::class, [
                'label' => 'Libellé (optionnel)',
                'required' => false,
                'attr' => ['class' => 'fr-input', 'placeholder' => 'Ex : matin, après-midi…'],
                'label_attr' => ['class' => 'fr-label'],
            ])

            ->add('endLabel', TextType::class, [
                'label' => 'Libellé fin (optionnel)',
                'required' => false,
                'attr' => ['class' => 'fr-input', 'placeholder' => 'Ex : pause, fin de journée…'],
                'label_attr' => ['class' => 'fr-label'],
            ])

            ->add('clear', SubmitType::class, [
                'label' => 'Vider jours sélectionnés',
                'attr'  => ['class' => 'fr-btn fr-btn--secondary'],
            ])
            ->add('generate', SubmitType::class, [
                'label' => 'Générer les créneaux',
                'attr'  => ['class' => 'fr-btn'],
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Enregistrer (unitaire)',
                'attr'  => ['class' => 'fr-btn fr-btn--primary'],
            ])


        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TimeBlock::class,
            'layout_timezone' => 'Europe/Paris',
        ]);
        $resolver->setAllowedTypes('layout_timezone', 'string');

    }
}
