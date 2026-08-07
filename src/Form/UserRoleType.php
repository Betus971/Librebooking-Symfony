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
                'label' => 'Désignation « Prestataire »',
                // P0.1 — ROLE_SUPER_ADMIN volontairement ABSENT (un super-admin ne
                // se crée jamais via le web).
                //
                // Les rôles « métier » (Admin Ressources, Admin Accueil, Agent
                // d'Accueil, Super-Admin) sont désormais attribués AUTOMATIQUEMENT
                // par le SSO d'après les groupes LDAP (profil A/B/C/D/E) et
                // RECALCULÉS à chaque requête : les afficher ici serait trompeur
                // (le SSO les réécrit systématiquement). Seul ROLE_PRESTATAIRE est
                // posé à la main, hors périmètre SSO → on ne garde que cette case.
                'choices' => [
                    'Prestataire (gère ses prestations et son agenda)' => 'ROLE_PRESTATAIRE',
                ],
                'multiple' => true, // reste un tableau de rôles (mappé sur User::roles)
                'expanded' => true, // Affiche une case à cocher
                'help' => "Les rôles d'administration et d'accueil sont attribués automatiquement par le SSO (groupes LDAP) et ne se règlent pas ici. Seule la désignation « Prestataire » est manuelle.",
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Enregistrer les droits',
                'attr' => ['class' => 'fr-btn']
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
   