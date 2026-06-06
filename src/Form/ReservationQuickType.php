<?php
namespace App\Form;

use App\Dto\ReservationQuickDto;
use App\Entity\Resource;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\All;

class ReservationQuickType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre de la réservation',
                'attr' => [
                    'class' => 'fr-input',
                    'placeholder' => 'Ex : Réunion d’équipe, Séance de sport…',
                ],
                'label_attr' => ['class' => 'fr-label'],
                'row_attr' => ['class' => 'fr-form-group'],
            ])
            ->add('resource', EntityType::class, [
                'label' => 'Ressource à réserver',
                'class' => Resource::class,
                'choice_label' => 'name',
                'required' => true,
                'placeholder' => 'Sélectionnez une ressource…',
                'attr' => ['class' => 'fr-select'],
                'label_attr' => ['class' => 'fr-label'],
                'row_attr' => ['class' => 'fr-form-group'],
            ])



            // --- DÉBUT ---
            ->add('startDate', DateType::class, [
                'label' => 'Date de début',
                'widget' => 'single_text',
                'mapped' => false, // Virtuel
                'attr' => ['class' => 'fr-input']
            ])
            ->add('startTime', TimeType::class, [
                'label' => 'Heure de début',
                'widget' => 'single_text',
                'mapped' => false,
                'attr' => ['class' => 'fr-input', 'step' => 1800] // 30 min
            ])

            // --- FIN (On rajoute la date de fin) ---
            ->add('endDate', DateType::class, [
                'label' => 'Date de fin',
                'widget' => 'single_text',
                'mapped' => false, // Virtuel
                'attr' => ['class' => 'fr-input']
            ])
            ->add('endTime', TimeType::class, [
                'label' => 'Heure de fin',
                'widget' => 'single_text',
                'mapped' => false,
                'attr' => ['class' => 'fr-input', 'step' => 1800]
            ])

            // 👥 LISTE DES PARTICIPANTS (Collection)
            ->add('participants', CollectionType::class, [
                'entry_type' => EmailType::class, // Ou un UserAutocompleteType plus tard
                'entry_options' => [
                    'label' => false,
                    'attr' => ['placeholder' => 'nom@domaine.fr']
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true, // Indispensable pour le JS
                'label' => 'Liste des participants',
                'mapped' => false, // Mettre à true si tu as une relation OneToMany en BDD
                'required' => false,
            ])


            // --------------------------------------
//            ->add('start', DateTimeType::class, [
//                'label' => 'Début',
//                'date_widget' => 'single_text',      // input type="date"
//                'time_widget' => 'single_text',      // input type="time"
//                'with_seconds' => false,
//                'input' => 'datetime_immutable',
//                'attr' => ['class' => 'fr-input'],
//            ])
//            ->add('end', DateTimeType::class, [
//                'label' => 'Fin',
//                'date_widget' => 'single_text',
//                'time_widget' => 'single_text',
//                'with_seconds' => false,
//                'input' => 'datetime_immutable',
//                'attr' => ['class' => 'fr-input'],
//            ])

            ->add('description', TextareaType::class, [
                'label' => 'Description (facultatif)',
                'required' => false,
                'attr' => [
                    'class' => 'fr-textarea',
                    'placeholder' => 'Précisez l’objectif ou les détails de votre réservation…',
                ],
                'label_attr' => ['class' => 'fr-label'],
                'row_attr' => ['class' => 'fr-form-group'],
            ])
        ->add('attachments', FileType::class, [
        'label' => 'Pièces justificatives',
        'multiple' => true, // <--- IMPORTANT : Autorise plusieurs fichiers
        'mapped' => false,
        'required' => false,
        'attr' => ['class' => 'fr-upload'],
        'constraints' => [
            new All([ // "All" applique la contrainte à chaque fichier envoyé
                'constraints' => [
                    new File([
                        'maxSize' => '25M',
                        'mimeTypes' => ['application/pdf', 'image/jpeg', 'image/png'],
                        'mimeTypesMessage' => 'Format invalide.',
                    ])
                ]
            ])
        ],
    ]);

    }

    public function configureOptions(OptionsResolver $r): void
    {
        $r->setDefaults(['data_class' => ReservationQuickDto::class]);
    }
}
