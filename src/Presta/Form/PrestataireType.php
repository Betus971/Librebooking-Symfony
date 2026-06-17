<?php

namespace App\Presta\Form;

use App\Presta\Entity\Prestataire;
use Symfony\Component\Form\AbstractType;
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
                'attr' => ['class' => 'fr-input'],
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'attr' => ['class' => 'fr-input'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description de votre profil (ex: Coach sportif certifié...)',
                'required' => false,
                'attr' => ['class' => 'fr-input', 'rows' => 4],
            ])
            ->add('photo', TextType::class, [
                'label' => 'URL de votre photo de profil',
                'required' => false,
                'attr' => ['class' => 'fr-input', 'placeholder' => 'https://...'],
            ])
            ->add('isActive', \Symfony\Component\Form\Extension\Core\Type\CheckboxType::class, [
                'label' => 'Profil public (décocher pour se mettre en vacances / invisible)',
                'required' => false,
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
