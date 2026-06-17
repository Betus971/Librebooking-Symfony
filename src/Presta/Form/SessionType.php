<?php

namespace App\Presta\Form;

use App\Presta\Entity\Service;
use App\Presta\Entity\Session;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SessionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $prestataire = $options['prestataire'];

        $builder
            ->add('service', EntityType::class, [
                'class' => Service::class,
                'choice_label' => 'libelle',
                'label' => 'Prestation de groupe',
                'query_builder' => function (EntityRepository $er) use ($prestataire) {
                    return $er->createQueryBuilder('s')
                        ->where('s.prestataire = :prestataire')
                        ->andWhere('s.type = :typeGroupe')
                        ->setParameter('prestataire', $prestataire)
                        ->setParameter('typeGroupe', Service::TYPE_GROUPE);
                },
                'attr' => ['class' => 'fr-select'],
            ])
            ->add('dateDebut', DateTimeType::class, [
                'label' => 'Début de la séance',
                'widget' => 'single_text',
                'attr' => ['class' => 'fr-input'],
            ])
            ->add('dateFin', DateTimeType::class, [
                'label' => 'Fin de la séance',
                'widget' => 'single_text',
                'attr' => ['class' => 'fr-input'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Session::class,
            'prestataire' => null, // Option personnalisée passée depuis le contrôleur
        ]);
    }
}
