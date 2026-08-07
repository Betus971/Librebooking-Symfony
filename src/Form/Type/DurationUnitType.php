<?php
namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class DurationUnitType extends AbstractType implements DataMapperInterface
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $labels  = ['m' => 'minutes', 'h' => 'heures', 'd' => 'jours'];
        $choices = [];
        foreach ($options['allowed_units'] as $u) {
            $choices[$labels[$u]] = $u;
        }

        $builder
            ->add('value', IntegerType::class, [
                'required' => false,
                'empty_data' => '',
                'attr' => ['min' => 0, 'step' => 1, 'class' => 'fr-input'],
                'label' => false,
            ])
            ->add('unit', ChoiceType::class, [
                'expanded'   => false,
                'multiple'   => false,
                'required'   => false,
                'choices'    => $choices,
                'data'       => $options['default_unit'],
                'empty_data' => $options['default_unit'],
                'label'      => false,
                // 👇 ON RETIRE LE BOUTON "None" INUTILE
                'placeholder' => false,
                // 👇 ON AJOUTE LA CLASSE DSFR POUR LE DESIGN
                'attr'       => ['class' => 'fr-select'],
            ])
            ->setDataMapper($this);
        // ----- Pré-remplissage en édition (minutes -> value + unit) -----
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options) {
            $minutes = $event->getData();               // int|null (minutes)
            $form    = $event->getForm();

            // défaut si vide
            $form->get('unit')->setData($options['default_unit']);

            if ($minutes === null || $minutes === '') {
                return;
            }

            $m = (int) $minutes;
            $unit = in_array('d', $options['allowed_units'], true) && $m % 1440 === 0 ? 'd'
                : (in_array('h', $options['allowed_units'], true) && $m % 60 === 0 ? 'h'
                    : 'm');
            $value = $unit === 'd' ? intdiv($m, 1440) : ($unit === 'h' ? intdiv($m, 60) : $m);

            $form->get('unit')->setData($unit);
            $form->get('value')->setData($value);
        });

        // ----- Sécurité POST : si le radio 'unit' n'est pas soumis, force default_unit -----
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($options) {
            $data = $event->getData() ?? [];
            if (!isset($data['unit']) || $data['unit'] === '' || $data['unit'] === null) {
                $data['unit'] = $options['default_unit'];
            }
            $event->setData($data);
        });

    }



    /** $viewData = int (minutes) | numeric-string | null */
    public function mapDataToForms($viewData, $forms): void
    {
        $forms = iterator_to_array($forms);

        $parent = $forms['unit']->getParent()->getConfig();
        $allowed = $parent->getOption('allowed_units', ['m','h','d']);
        $default = $parent->getOption('default_unit', 'm');

        if (empty($allowed)) {
            throw new \RuntimeException('Aucune unité autorisée n\'est définie.');
        }

        // Normalise
        if ($viewData === '' || $viewData === null) {
            $forms['value']->setData(null);
            $forms['unit']->setData(in_array($default, $allowed, true) ? $default : $allowed[0]);
            return;
        }
        if (is_numeric($viewData)) {
            $viewData = (int) $viewData;
        } else {
            // Donnée inattendue : fallback "vide"
            $forms['value']->setData(null);
            $forms['unit']->setData(in_array($default, $allowed, true) ? $default : $allowed[0]);
            return;
        }

        // Choix de l'unité "propre"
        if (in_array('d', $allowed, true) && $viewData % 1440 === 0) {
            $unit  = 'd'; $value = intdiv($viewData, 1440);
        } elseif (in_array('h', $allowed, true) && $viewData % 60 === 0) {
            $unit  = 'h'; $value = intdiv($viewData, 60);
        } elseif (in_array('m', $allowed, true)) {
            $unit  = 'm'; $value = $viewData;
        } else {
            $unit   = $allowed[0];
            $factor = $unit === 'd' ? 1440 : 60;
            $value  = (int) ceil($viewData / $factor);
        }

        $forms['value']->setData($value);
        $forms['unit']->setData($unit);
    }

    public function mapFormsToData($forms, &$viewData): void
    {
        $forms = iterator_to_array($forms);
        $rawValue = $forms['value']->getData();   // '' si vide
        $unit     = $forms['unit']->getData();

        $parent = $forms['unit']->getParent()->getConfig();
        $allowed = $parent->getOption('allowed_units', ['m','h','d']);
        $default = $parent->getOption('default_unit', 'm');

        // Valeur vide => null en base
        if ($rawValue === '' || $rawValue === null) {
            $viewData = null;
            return;
        }

        if (!is_numeric($rawValue)) {
            throw new \InvalidArgumentException('La valeur doit être un nombre.');
        }
        $v = (int) $rawValue;
        if ($v < 0) {
            throw new \InvalidArgumentException('La valeur doit être positive ou nulle.');
        }

        // Clamp de l’unité
        if (!$unit) { $unit = $default; }
        if (!in_array($unit, $allowed, true)) {
            $unit = $allowed[0];
        }

        $viewData = match ($unit) {
            'd' => $v * 1440,
            'h' => $v * 60,
            default => $v,
        };
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'required' => false,
            'label' => null,
            'help' => null,
            'allowed_units' => ['m', 'h', 'd'],
            'default_unit' => 'm',
        ]);

        $resolver->setAllowedValues('allowed_units', function ($v) {
            if (!is_array($v) || empty($v)) return false;
            foreach ($v as $u) if (!in_array($u, ['m', 'h', 'd'], true)) return false;
            return true;
        });

        $resolver->setAllowedValues('default_unit', ['m', 'h', 'd']);
    }

    public function getBlockPrefix(): string
    {
        return 'duration_unit';
    }
}
