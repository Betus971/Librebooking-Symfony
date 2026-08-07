<?php

namespace App\Form;

use App\Entity\Accessoire;
use App\Entity\Resource;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

class AccessoireType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom de l\'accessoire',
                'help'  => 'Ex. : Micro sans fil, Pupitre, Rallonge électrique. Le nom doit être unique.',
                'attr'  => ['class' => 'fr-input', 'placeholder' => 'Ex. : Micro sans fil'],
            ])
            ->add('quantiteDisponible', IntegerType::class, [
                'label'    => 'Quantité disponible en stock',
                'help'     => 'Laisser vide = stock illimité (∞).',
                'required' => false,
                'attr'     => ['class' => 'fr-input', 'min' => 0, 'placeholder' => 'Illimité si vide'],
                'constraints' => [new PositiveOrZero(message: 'La quantité ne peut pas être négative.')],
            ])
            ->add('description', TextareaType::class, [
                'label'    => 'Description (facultatif)',
                'required' => false,
                'attr'     => ['class' => 'fr-input', 'rows' => 2, 'placeholder' => 'Précisions éventuelles (modèle, emplacement…)'],
            ])
            ->add('resources', EntityType::class, [
                'label'         => 'Ressources concernées',
                'help'          => 'Laisser vide = disponible pour TOUTES les ressources. Sinon, cochez les ressources pour lesquelles l\'accessoire est proposé.',
                'class'         => Resource::class,
                'choice_label'  => 'name',
                'query_builder' => fn (EntityRepository $r) => $r->createQueryBuilder('res')->orderBy('res.name', 'ASC'),
                'multiple'      => true,
                'expanded'      => true, // cases à cocher (au lieu d'un select multiple Ctrl/Cmd)
                'required'      => false,
            ])
            ->add('actif', CheckboxType::class, [
                'label'    => 'Actif (proposé au choix lors d\'une réservation)',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Accessoire::class,
        ]);
    }
}
