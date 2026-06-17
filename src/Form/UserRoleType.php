<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserRoleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('roles', ChoiceType::class, [
                'label' => 'Attribution des rôles',
                // P0.1 — ROLE_SUPER_ADMIN volontairement ABSENT de cette liste.
                // Un super-admin ne se crée jamais via le formulaire web (sinon
                // n'importe quel gestionnaire pourrait s'auto-promouvoir). Il se
                // provisionne en base à la main / via console / via mapping SSO.
                'choices' => [
                    'Admin Ressources (Salles, Véhicules)' => 'ROLE_ADMIN_RESSOURCE',
                    'Admin Badges (Création & Suppression)' => 'ROLE_ADMIN_BADGE',
                    'Agent d\'Accueil (Attribution badge uniquement)' => 'ROLE_AGENT_ACCUEIL',
                ],
                'multiple' => true, // On peut avoir plusieurs casquettes
                'expanded' => true, // Affiche des Checkboxes (pas une liste déroulante)
                'help' => 'Cochez les responsabilités de cet utilisateur.',
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Enregistrer les droits',
                'attr' => ['class' => 'fr-btn'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
