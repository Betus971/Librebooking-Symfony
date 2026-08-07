<?php

namespace App\Presta\Form;

use App\Presta\Entity\PrestaCategorie;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class PrestaCategorieType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom de la catégorie',
                'attr'  => ['class' => 'fr-input', 'placeholder' => 'Ex. TIR, CCPM, Coiffure…'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le nom de la catégorie est obligatoire.'),
                    new Assert\Length(max: 100),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label'    => 'Description (facultatif)',
                'required' => false,
                'attr'     => ['class' => 'fr-input', 'rows' => 3],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PrestaCategorie::class,
        ]);
    }
}
