<?php

namespace App\Form;

use App\Config\SettingDefinitions;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire dynamique : un champ par réglage du registre {@see SettingDefinitions},
 * du bon type. On manipule un simple tableau clé => valeur (pas d'entité).
 */
final class ConfigurationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach (SettingDefinitions::SETTINGS as $cle => [$section, $label, $type, $default]) {
            $builder->add($cle, match ($type) {
                'bool' => CheckboxType::class,
                'int'  => IntegerType::class,
                'text' => TextareaType::class,
                default => TextType::class,
            }, [
                'label'    => $label,
                'required' => false,
                // La valeur ('data') est fournie par le contrôleur via le tableau $current.
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // On manipule un tableau associatif, pas un objet.
        $resolver->setDefaults(['data_class' => null]);
    }
}
