<?php

namespace App\Form;

use App\Entity\Layout;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LayoutType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du layout',
            ])
            ->add('timezone', TimezoneType::class, [
                'label' => 'Fuseau horaire',
                'placeholder' => 'Choisissez un fuseau',
                // Filtre possible : 'regions' => \DateTimeZone::EUROPE,
                'preferred_choices' => ['Europe/Paris'],
                'help' => 'Ex. Europe/Paris, America/New_York… (IANA)',
            ])
            ->add('layoutType', ChoiceType::class, [
                'label' => 'Type de layout',
                'choices' => [
                    'Grille horaire (minutes/heures)' => Layout::TYPE_TIMES,
                    'Grille par périodes nommées'     => Layout::TYPE_PERIODS,
                ],
                'expanded' => false,
                'multiple' => false,
                'help' => "Times = créneaux par minute/heure • Periods = séquences nommées (ex. 'Matin', 'Cours A').",
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Layout::class,
        ]);
    }
}
