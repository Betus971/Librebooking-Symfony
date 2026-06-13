<?php

namespace App\Form;

use App\Entity\TimeBlock;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Édition d'UN créneau (TimeBlock) au sein du layout, pensé pour la
 * CollectionField d'EasyAdmin. Tous les champs sont mappés sur l'entité.
 */
class TimeBlockAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dayOfWeek', ChoiceType::class, [
                'label'       => new TranslatableMessage('entity.timeblock.day'),
                'required'    => false,
                'placeholder' => new TranslatableMessage('entity.timeblock.day_all'),
                // Les clés (libellés) sont des clés de traduction, traduites
                // automatiquement par Symfony (choice_translation_domain = messages).
                'choices'     => [
                    'common.weekday.monday'    => 1,
                    'common.weekday.tuesday'   => 2,
                    'common.weekday.wednesday' => 3,
                    'common.weekday.thursday'  => 4,
                    'common.weekday.friday'    => 5,
                    'common.weekday.saturday'  => 6,
                    'common.weekday.sunday'    => 0,
                ],
                'choice_translation_domain' => 'messages',
            ])
            ->add('startTime', TimeType::class, [
                'label'        => new TranslatableMessage('entity.timeblock.start'),
                'widget'       => 'single_text',
                'with_seconds' => false,
                'input'        => 'datetime_immutable',
            ])
            ->add('endTime', TimeType::class, [
                'label'        => new TranslatableMessage('entity.timeblock.end'),
                'widget'       => 'single_text',
                'with_seconds' => false,
                'input'        => 'datetime_immutable',
            ])
            ->add('availabilityCode', ChoiceType::class, [
                'label'   => new TranslatableMessage('entity.timeblock.availability'),
                'choices' => [
                    'entity.timeblock.open'   => TimeBlock::OPEN,
                    'entity.timeblock.closed' => TimeBlock::CLOSED,
                ],
                'choice_translation_domain' => 'messages',
            ])
            ->add('label', TextType::class, [
                'label'    => new TranslatableMessage('entity.timeblock.label'),
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TimeBlock::class,
        ]);
    }
}
