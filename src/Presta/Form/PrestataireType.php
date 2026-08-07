<?php

namespace App\Presta\Form;

use App\Presta\Entity\Prestataire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PrestataireType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom de famille',
                'attr' => ['class' => 'fr-input']
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'attr' => ['class' => 'fr-input']
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description de votre profil (ex: Coach sportif certifié...)',
                'required' => false,
                'attr' => ['class' => 'fr-input', 'rows' => 4]
            ])
            ->add('photo', TextType::class, [
                'label' => 'URL de votre photo de profil',
                'required' => false,
                'attr' => ['class' => 'fr-input', 'placeholder' => 'https://...']
            ])
            ->add('isActive', \Symfony\Component\Form\Extension\Core\Type\CheckboxType::class, [
                'label' => 'Profil public (décocher pour se mettre en vacances / invisible)',
                'required' => false,
                'attr' => ['class' => 'fr-toggle__input']
            ])
            ->add('horizonJours', IntegerType::class, [
                'label' => 'Réservable jusqu\'à combien de jours à l\'avance ?',
                'help' => 'Fenêtre glissante : les clients réservent sur les N prochains jours. Elle avance automatiquement chaque jour. Ex. 14 = 2 semaines.',
                'attr' => ['class' => 'fr-input', 'min' => 1, 'max' => 365],
            ])
            ->add('delaiAnnulationHeures', IntegerType::class, [
                'label' => 'Délai d\'annulation client (en heures)',
                'help' => 'Un client ne peut plus annuler à moins de N heures du rendez-vous. Ex. 48 = 2 jours. Mettre 0 pour autoriser l\'annulation jusqu\'au dernier moment. (Vous, prestataire, n\'êtes jamais bloqué.)',
                'attr' => ['class' => 'fr-input', 'min' => 0, 'max' => 720],
            ])
            ->add('unRdvActifParClient', \Symfony\Component\Form\Extension\Core\Type\CheckboxType::class, [
                'label' => 'Un seul rendez-vous actif par client',
                'required' => false,
                'help' => 'Si activé, un client ne peut avoir qu\'un seul RDV individuel en cours chez vous à la fois : il doit annuler (ou attendre qu\'il soit passé) avant d\'en reprendre un. Ne concerne pas les séances de groupe.',
                'attr' => ['class' => 'fr-toggle__input'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Prestataire::class,
        ]);
    }
}
