<?php

namespace App\Presta\Form;

use App\Presta\Entity\PlageHoraire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PlageHoraireType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'];

        if (!$isEdit) {
            $builder->add('joursSemaine', ChoiceType::class, [
                'mapped' => false,
                'label' => 'Jours concernés',
                'expanded' => true,
                'multiple' => true,
                'choices' => [
                    'Lundi' => 1,
                    'Mardi' => 2,
                    'Mercredi' => 3,
                    'Jeudi' => 4,
                    'Vendredi' => 5,
                    'Samedi' => 6,
                    'Dimanche' => 7,
                ],
                'attr' => ['class' => 'fr-checkbox-group']
            ]);
        } else {
            $builder->add('jourSemaine', ChoiceType::class, [
                'label' => 'Jour de la semaine',
                'choices' => [
                    'Lundi' => 1,
                    'Mardi' => 2,
                    'Mercredi' => 3,
                    'Jeudi' => 4,
                    'Vendredi' => 5,
                    'Samedi' => 6,
                    'Dimanche' => 7,
                ],
                'attr' => ['class' => 'fr-select']
            ]);
        }

        $builder
            ->add('heureDebut', TimeType::class, [
                'label' => 'Heure de début',
                'widget' => 'single_text',
                'attr' => ['class' => 'fr-input']
            ])
            ->add('heureFin', TimeType::class, [
                'label' => 'Heure de fin',
                'widget' => 'single_text',
                'attr' => ['class' => 'fr-input']
            ])
            ->add('dateDebut', DateType::class, [
                'label' => 'Valable à partir du (optionnel)',
                'required' => false,
                'widget' => 'single_text',
                'help' => 'Laissez vide pour une plage récurrente sans limite de dates.',
                'attr' => ['class' => 'fr-input'],
            ])
            ->add('dateFin', DateType::class, [
                'label' => 'Valable jusqu\'au (optionnel)',
                'required' => false,
                'widget' => 'single_text',
                'help' => 'Laissez vide pour une plage sans date de fin.',
                'attr' => ['class' => 'fr-input'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PlageHoraire::class,
            'is_edit' => false,
        ]);
    }
}
