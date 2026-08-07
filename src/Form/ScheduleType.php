<?php

namespace App\Form;

use App\Entity\Layout;
use App\Entity\Schedule;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotNull;

class ScheduleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Nom'])
            // Champs « weekdayStart » (jour de début de semaine) et « daysVisible »
            // (nb de jours visibles) retirés du formulaire : ils ne servaient qu'au
            // calendrier de disponibilités, supprimé de l'application. L'entité garde
            // ses valeurs par défaut (weekdayStart=1 lundi, daysVisible=7).
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
            ->add('startDate', DateType::class, [
                'widget' => 'single_text',
                'required' => true,
                'label' => 'Début *',
                'help' => 'Obligatoire : date à partir de laquelle le planning est actif.',
                'constraints' => [new NotNull(['message' => 'La date de début est obligatoire.'])],
            ])
            ->add('endDate', DateType::class, [
                'widget' => 'single_text',
                'required' => true,
                'label' => 'Fin *',
                'help' => 'Obligatoire : date jusqu\'à laquelle le planning est actif.',
                'constraints' => [new NotNull(['message' => 'La date de fin est obligatoire.'])],
            ])
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
