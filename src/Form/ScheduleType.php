<?php

namespace App\Form;

use App\Entity\Schedule;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ScheduleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Nom'])
            ->add('weekdayStart', ChoiceType::class, [
                'label' => 'Jour de début de semaine',
                'choices' => [
                    'Lundi' => 1,
                    'Mardi' => 2,
                    'Mercredi' => 3,
                    'Jeudi' => 4,
                    'Vendredi' => 5,
                    'Samedi' => 6,
                    'Dimanche' => 0,
                ],
                'placeholder' => 'Choisir un jour',
            ])
            ->add('daysVisible', IntegerType::class, ['label' => 'Nb jours visibles', 'empty_data' => '7'])


            ->add('timezone', TimezoneType::class, [
                'mapped' => false,
                'label' => 'Fuseau horaire',
                'placeholder' => 'Choisir un fuseau',
                'preferred_choices' => ['Europe/Paris'],
                'data' => $options['layout_timezone'],     // 👈 injecté par le contrôleur
                'attr' => ['class' => 'fr-select'],
                'label_attr' => ['class' => 'fr-label'],
            ])
            ->add('published', CheckboxType::class, ['required' => false, 'label' => 'Publié'])
            ->add('allowCalendarSubscription', CheckboxType::class, ['required' => false, 'label' => 'Abonnement iCal'])
            ->add('startDate', DateType::class, ['widget' => 'single_text', 'required' => false, 'label' => 'Début'])
            ->add('endDate', DateType::class, ['widget' => 'single_text', 'required' => false, 'label' => 'Fin'])
            ->add('allowConcurrentBookings', CheckboxType::class, ['required' => false, 'label' => 'Réservations concurrentes'])
            ->add('totalConcurrentReservations', IntegerType::class, ['required' => false, 'label' => 'Max réservations simultanées'])
            ->add('maxResourcesPerReservation', IntegerType::class, ['required' => false, 'label' => 'Max ressources par résa'])
            ->add('notes', TextType::class, ['required' => false, 'label' => 'Notes']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Schedule::class,
            'layout_timezone' => 'Europe/Paris',

        ]);
        $resolver->setAllowedTypes('layout_timezone', 'string');
    }
}
