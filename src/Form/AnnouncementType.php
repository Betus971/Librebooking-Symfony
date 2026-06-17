<?php

namespace App\Form;

use App\Entity\Announcement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AnnouncementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('message', \Symfony\Component\Form\Extension\Core\Type\TextareaType::class, [
                'label' => 'Message de l\'annonce',
                'attr' => ['rows' => 4, 'class' => 'fr-input'],
            ])
            ->add('startDate', \Symfony\Component\Form\Extension\Core\Type\DateTimeType::class, [
                'label' => 'Date de début',
                'widget' => 'single_text',
                'required' => false,
                'attr' => ['class' => 'fr-input'],
                'help' => 'Laissez vide pour afficher immédiatement.',
            ])
            ->add('endDate', \Symfony\Component\Form\Extension\Core\Type\DateTimeType::class, [
                'label' => 'Date de fin',
                'widget' => 'single_text',
                'attr' => ['class' => 'fr-input'],
            ])
            ->add('priority', \Symfony\Component\Form\Extension\Core\Type\ChoiceType::class, [
                'label'   => 'Niveau de l\'annonce',
                'choices' => [
                    'Information (bleu)'      => 1,
                    'Succès (vert)'           => 2,
                    'Avertissement (orange)'  => 3,
                    'Urgent (rouge)'          => 4,
                ],
                'attr' => ['class' => 'fr-select'],
                'help' => 'Détermine la couleur et le ton du bandeau affiché en page d\'accueil.',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Announcement::class,
        ]);
    }
}
