<?php

namespace App\Twig;

use App\Service\Settings;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Expose la fonction Twig `setting('cle')` pour lire un réglage de l'application.
 *
 *   <title>{{ setting('general.app_title') }}</title>
 */
final class SettingsExtension extends AbstractExtension
{
    public function __construct(private readonly Settings $settings)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('setting', $this->settings->get(...)),
        ];
    }
}
