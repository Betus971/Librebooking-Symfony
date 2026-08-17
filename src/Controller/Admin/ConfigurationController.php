<?php

namespace App\Controller\Admin;

use App\Config\SettingDefinitions;
use App\Form\ConfigurationType;
use App\Service\Settings;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Écran d'administration des réglages de l'application (/admin/configuration).
 * Réservé au super-administrateur (également protégé par access_control).
 */
#[Route('/admin/configuration', name: 'admin_configuration', methods: ['GET', 'POST'])]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class ConfigurationController extends AbstractController
{
    public function __invoke(Request $request, Settings $settings, LoggerInterface $logger): Response
    {
        // Valeurs actuelles (surcharge OU défaut) pour préremplir le formulaire.
        // Les clés du form encodent "." en "__" (noms de champs Symfony valides).
        $current = [];
        foreach (array_keys(SettingDefinitions::SETTINGS) as $cle) {
            $current[str_replace('.', '__', $cle)] = $settings->get($cle);
        }

        $form = $this->createForm(ConfigurationType::class, $current);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($form->getData() as $name => $valeur) {
                $settings->set(str_replace('__', '.', $name), $valeur);
            }
            $logger->info('config_updated', ['by' => $this->getUser()?->getUserIdentifier()]);
            $this->addFlash('success', 'Configuration enregistrée.');

            return $this->redirectToRoute('admin_configuration');
        }

        return $this->render('admin/configuration.html.twig', [
            'form'     => $form,
            'sections' => SettingDefinitions::bySection(),
        ]);
    }
}
