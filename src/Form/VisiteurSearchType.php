<?php
namespace App\Form;

use App\Dto\VisiteurSearchDto;
use Doctrine\DBAL\Types\DateTimeType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Validator\Constraints\DateTime;

class VisiteurSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'required' => false,
                'label' => 'Nom',
            ])
            ->add('prenom', TextType::class, [
                'required' => false,
                'label' => 'Prénom',
            ])
            ->add('datearriveeMin', DateType::class, [
                'widget' => 'single_text',
                'required' => false,
                'label' => 'Du ',
            ])
            ->add('datearriveeMax', DateType::class, [
                'widget' => 'single_text',
                'required' => false,
                'label' => 'Au',
            ])
            ->add('search', SubmitType::class, [
                'label' => 'Rechercher'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => VisiteurSearchDto::class,
            'method' => 'GET', // Utilisation de GET pour des filtres
            'csrf_protection' => false, // Pas nécessaire pour un formulaire de recherche
        ]);
    }
}