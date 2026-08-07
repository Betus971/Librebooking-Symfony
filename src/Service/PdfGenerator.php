<?php

namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\Response;

/**
 * Génération de PDF à partir de HTML (Dompdf), factorisée pour les exports.
 *
 * Sécurité : `isRemoteEnabled` reste DÉSACTIVÉ (P1.4) — pas de chargement de
 * ressource distante (anti-SSRF / lecture de fichier local via du HTML
 * semi-maîtrisé). Les images embarquées via data-URI (base64) restent
 * supportées, ce qui permet d'insérer les graphiques capturés côté navigateur.
 */
final class PdfGenerator
{
    /**
     * Rend un HTML complet en réponse PDF (affichage inline dans le navigateur).
     */
    public function inlinePdf(string $html, string $filename, string $orientation = 'portrait'): Response
    {
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', $orientation);
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '-' . date('Y-m-d_H-i') . '.pdf"',
        ]);
    }

    /**
     * Valide une image fournie par le client sous forme de data-URI PNG.
     * Retourne la valeur si elle est bien un PNG base64 valide et raisonnable,
     * sinon null (l'appelant rend alors le PDF sans le graphique).
     */
    public function sanitizePngDataUri(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $prefix = 'data:image/png;base64,';
        if (!str_starts_with($value, $prefix)) {
            return null;
        }
        // Garde-fou taille (~6 Mo) contre un POST abusif.
        if (strlen($value) > 6_000_000) {
            return null;
        }
        $b64 = substr($value, strlen($prefix));

        return base64_decode($b64, true) !== false ? $value : null;
    }
}
