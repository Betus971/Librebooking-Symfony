<?php

namespace App\Form;

use App\Entity\Resource;
use App\Entity\ResourceCategory;
use App\Entity\Schedule;
use App\Form\Type\DurationSimpleType;
use App\Entity\ResourceGroup;
use App\Form\Type\DurationUnitType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

class ResourceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $toggle = fn(string $label) => [
            'label' => $label,
            'required' => false,
            // PAS de classe fr-toggle, PAS de data-dsfr-toggle
            'attr' => ['class' => 'fr-checkbox'],  // ou même [] si tu veux 0 style DSFR
            'label_attr' => ['class' => 'fr-label'],
        ];

        // Options communes pour les inputs/textarea DSFR
        $inputOptions = fn(string $label, bool $required = false, ?string $help = null) => [
            'label' => $label,
            'required' => $required,
            'attr' => ['class' => 'fr-input'],
            'label_attr' => ['class' => 'fr-label'],
            'help' => $help,
        ];

        // Options pour les EntityType (select)
        $entityOptions = fn(string $label, string $placeholder, string $class, bool $required = false) => [
            'class' => $class,
            'choice_label' => 'name',
            'placeholder' => $placeholder,
            'required' => $required,
            'attr' => ['class' => 'fr-select'],
            'label_attr' => ['class' => 'fr-label'],
            'label' => $label,
        ];
        $builder
            ->add('photo', FileType::class, [
                'label' => 'Photo de la ressource (JPEG/PNG)',
                'mapped' => false, // Important : ce n'est pas lié directement à la BDD
                'required' => false,
                'attr' => ['class' => 'fr-upload'], // Style DSFR si tu veux
                'constraints' => [
                    new Image([
                        'maxSize' => '10M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp'],
                        'maxSizeMessage' => 'Image trop lourde (max 5Mo).',
                    ]),
                ],
            ])
            ->add('name', TextType::class, $inputOptions('Nom *', true))
            ->add('schedule', EntityType::class, $entityOptions(
                'Créneau *',
                'Sélectionnez un créneau',
                Schedule::class,
                true
            ))
            ->add('category', EntityType::class, $entityOptions(
                'Catégorie',
                'Aucune catégorie',
                ResourceCategory::class,
                false
            ))
            ->add('resourceGroup', EntityType::class, $entityOptions(
                'Équipe responsable *',
                'Sélectionnez l\'équipe (ex: Logistique)',
                ResourceGroup::class,
                true // true = On oblige à choisir une équipe
            ))
            ->add('location', TextType::class, $inputOptions('Lieu', false))
            ->add('contactInfo', TextType::class, $inputOptions('Informations de contact', false, 'Ex. email/téléphone du référent'))
            ->add('description', TextareaType::class, array_merge(
                $inputOptions('Description', false),
                ['attr' => ['class' => 'fr-textarea']]
        ))
        ->add('notes', TextareaType::class, [
            'label'      => 'Équipements & caractéristiques',
            'required'   => false,
            'attr'       => [
                'class'       => 'fr-textarea',
                'placeholder' => 'Rétroprojecteur, Wifi, Tableau blanc, Système audio, Vidéoconférence, Micro',
                'rows'        => 3,
            ],
            'label_attr' => ['class' => 'fr-label'],
            'help'       => 'Listez les équipements séparés par des virgules. Ex : Rétroprojecteur, Wifi, Tableau blanc',
            'row_attr'   => ['class' => 'fr-input-group'],
        ])
        ->add('maxParticipants', IntegerType::class, $inputOptions('Participants maximum', false))
        ->add('isActive', CheckboxType::class, $toggle('Actif'));

// --- Règles d’accès (essentiel) ---
        $builder
            ->add('requiresApproval', CheckboxType::class, $toggle('Approbation requise'))
            ->add('allowMultiday', CheckboxType::class, $toggle('Autoriser les réservations multi-jours'))


            // --- Durée & délais (existants) ---
            ->add('minDuration', DurationUnitType::class, [
                'label' => 'Durée minimale',
                'required' => false,
                'attr' => [
                    'class' => 'fr-input-group',
                    'data-allowed-units' => json_encode(['m', 'h', 'd']), // Ajoute cette ligne
                ],
                'label_attr' => ['class' => 'fr-label'],
                'allowed_units' => ['m', 'h', 'd'],
                'default_unit' => 'm',
                'help' => 'Laisser vide = pas de durée minimale (ex. 30 min / 1 h / 1 j).',
            ])
            ->add('minIncrement', DurationUnitType::class, [
                'label' => 'Pas de créneau',
                'required' => false,
                'attr' => ['class' => 'fr-input-group'],
                'label_attr' => ['class' => 'fr-label'],
                'allowed_units' => ['m', 'h'],
                'default_unit' => 'm',
                'help' => 'Laisser vide = aucun pas imposé (ex. 15 min ou 1 h).',
            ])
            ->add('maxDuration', DurationUnitType::class, [
                'label' => 'Durée maximale',
                'required' => false,
                'mapped' => true, // Active le mapping automatique via DataMapper
                'attr' => ['class' => 'fr-input-group'],
                'label_attr' => ['class' => 'fr-label'],
                'allowed_units' => ['m', 'h', 'd'],
                'default_unit' => 'h',
                'help' => 'Laisser vide = pas de durée maximale (ex. 4 h / 2 j).',
            ])


            // --- Nouveaux : délais entre réservations & no-show ---
            ->add('bufferTime', DurationUnitType::class, [
                'label' => 'Temps tampon entre réservations',
                'required' => false,
                'attr' => ['class' => 'fr-input-group'],
                'label_attr' => ['class' => 'fr-label'],
                'allowed_units' => ['m','h'],
                'default_unit' => 'm',
                'help' => 'Délai minimal entre la fin d’une résa et le début de la suivante. Laisser vide = aucun.',
            ])
            ->add('autoReleaseMinutes', DurationUnitType::class, [
                'label' => 'Libération automatique (no-show)',
                'required' => false,
                'attr' => ['class' => 'fr-input-group'],
                'label_attr' => ['class' => 'fr-label'],
                'allowed_units' => ['m','h'],
                'default_unit' => 'm',
                'help' => 'Annule/libère si non démarrée après ce délai. Laisser vide = désactivé.',
            ])



            // --- Nouveaux : préavis & fenêtre d’anticipation ---
            ->add('minNoticeTimeAdd', DurationUnitType::class, [
                'label' => 'Préavis de création',
                'required' => false,
                'attr' => ['class' => 'fr-input-group'],
                'label_attr' => ['class' => 'fr-label'],
                'allowed_units' => ['m','h','d'],
                'default_unit' => 'h',
                'help' => 'Ex. 1 j : créer au moins 1 jour avant. Laisser vide = pas de préavis.',
            ])
            ->add('minNoticeTimeUpdate', DurationUnitType::class, [
                'label' => 'Préavis de modification',
                'required' => false,
                'attr' => ['class' => 'fr-input-group'],
                'label_attr' => ['class' => 'fr-label'],
                'allowed_units' => ['m','h','d'],
                'default_unit' => 'h',
                'help' => 'Ex. 7 j : modifier au moins 7 jours avant. Laisser vide = pas de préavis.',
            ])
            ->add('minNoticeTimeDelete', DurationUnitType::class, [
                'label' => 'Préavis d’annulation',
                'required' => false,
                'attr' => ['class' => 'fr-input-group'],
                'label_attr' => ['class' => 'fr-label'],
                'allowed_units' => ['m','h','d'],
                'default_unit' => 'h',
                'help' => 'Ex. 1 j : annuler au moins 1 jour avant. Laisser vide = pas de préavis.',
            ])
            ->add('maxNoticeTime', DurationUnitType::class, [
                'label' => 'Anticipation maximale (au plus tôt)',
                'required' => false,
                'attr' => ['class' => 'fr-input-group'],
                'label_attr' => ['class' => 'fr-label'],
                'allowed_units' => ['d','h','m'],
                'default_unit' => 'd',
                'help' => 'Ex. 90 j : on ne peut pas réserver plus de 90 jours à l’avance. Laisser vide = illimité.',
            ]);
 }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Resource::class,
        ]);
    }
}
