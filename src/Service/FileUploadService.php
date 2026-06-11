<?php

namespace App\Service;

use App\Service\Exception\InvalidMimeTypeException;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Centralise l'upload de fichiers : validation du type MIME réel (finfo),
 * génération d'un nom de fichier sûr et unique, puis déplacement vers le
 * répertoire cible.
 *
 * Remplace la logique d'upload qui était dupliquée dans plusieurs contrôleurs.
 */
final class FileUploadService
{
    public function __construct(private readonly SluggerInterface $slugger)
    {
    }

    /**
     * Valide puis déplace le fichier ; retourne le nom de fichier généré.
     *
     * La validation s'appuie sur le type MIME réel (contenu, via finfo), ce qui
     * résiste au spoofing d'extension / de Content-Type.
     *
     * @param string[] $allowedMimes types MIME réellement autorisés
     *
     * @throws InvalidMimeTypeException si le type MIME détecté n'est pas autorisé
     * @throws FileException            si le déplacement du fichier échoue
     */
    public function upload(UploadedFile $file, string $directory, array $allowedMimes): string
    {
        // Capture du MIME AVANT déplacement (après move(), le fichier temporaire
        // n'existe plus et getMimeType() casse).
        $detectedMime = $file->getMimeType();
        if (!in_array($detectedMime, $allowedMimes, true)) {
            throw new InvalidMimeTypeException((string) $detectedMime);
        }

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();

        $file->move($directory, $newFilename);

        return $newFilename;
    }
}
