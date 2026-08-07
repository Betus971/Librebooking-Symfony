<?php

namespace App\Presta\Form;

use App\Presta\Entity\PrestaCategorie;
use App\Presta\Entity\Service;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ServiceType extends AbstractType
{
    /**
     * Palette de couleurs illustratives DSFR proposée aux prestataires pour
     * distinguer leurs prestations dans l'agenda. Nom → hex.
     */
    public const PALETTE = [
        'Glycine'          => '#6E445A',
        'Macaron'          => '#E18B76',
        'Émeraude'         => '#009081',
        'Menthe'           => '#37635F',
        'Tournesol'        => '#C3992A',
        'Café Crème'       => '#8D533E',
        'Aubergine'        => '#5B3A6E',
        'Tilleul Verveine' => '#66673D',
        'Framboise'        => '#A94645',
        'Gris Ardoise'     => '#666666',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('libelle', TextType::class, [
                'label' => 'Nom de la prestation',
                'help' => 'Ex. : Coupe homme, Séance CrossFit, Bilan individuel.',
                'attr' => ['class' => 'fr-input', 'placeholder' => 'Nom visible par les clients']
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'help' => 'Facultatif. Affichée sur la fiche de la prestation.',
                'attr' => ['class' => 'fr-input', 'rows' => 3]
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'choices' => [
                    'Individuel' => Service::TYPE_INDIVIDUEL,
                    'Groupe' => Service::TYPE_GROUPE,
                ],
                'attr' => ['class' => 'fr-select']
            ])
            ->add('dureeMinutes', IntegerType::class, [
                'label' => 'Durée',
                'help' => 'En minutes.',
                'attr' => ['class' => 'fr-input', 'min' => 5]
            ])
            ->add('capaciteMax', IntegerType::class, [
                'label' => 'Capacité',
                'help' => 'Nombre de personnes max.',
                'attr' => ['class' => 'fr-input', 'min' => 1]
            ])
            ->add('categorie', EntityType::class, [
                'class' => PrestaCategorie::class,
                'choice_label' => 'nom',
                'label' => 'Catégorie',
                'placeholder' => 'Choisissez une catégorie',
                'required' => true,
                'attr' => ['class' => 'fr-select'],
                'help' => 'Définie par l\'administration. Aide les usagers à trouver votre prestation par thème.',
                'constraints' => [
                    new Assert\NotNull(message: 'Veuillez sélectionner une catégorie.'),
                ],
            ])
            ->add('couleur', ChoiceType::class, [
                'label'    => 'Couleur dans l\'agenda',
                'help'     => 'Couleur d\'affichage de cette prestation dans votre agenda.',
                'choices'  => self::PALETTE,
                'expanded' => true,
                'multiple' => false,
                'required' => false,
                'placeholder' => false,
                // Le hex est exposé pour dessiner la pastille dans le template.
                'choice_attr' => static fn (?string $hex) => ['data-couleur' => $hex ?? ''],
            ])
            ->add('requiresApproval', \Symfony\Component\Form\Extension\Core\Type\CheckboxType::class, [
                'label' => 'Approbation requise',
                'required' => false,
                'help' => 'Si coché, chaque réservation de cette prestation reste « en attente » jusqu\'à ce que vous la validiez (le créneau est bloqué entre-temps).',
                'attr' => ['class' => 'fr-toggle__input']
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
