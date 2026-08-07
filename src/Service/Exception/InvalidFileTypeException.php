<?php

namespace App\Service\Exception;

/**
 * Levée par {@see \App\Service\FileUploadService::upload()} quand le type MIME
 * réel d'un fichier déposé n'est pas dans la liste blanche fournie.
 */
final class InvalidFileTypeException extends \RuntimeException
{
    public function __construct(private readonly string $detectedMime)
    {
        parent::__construct(sprintf('Type de fichier non autorisé : %s', $detectedMime));
    }

    public function getDetectedMime(): string
    {
        return $this->detectedMime;
    }
}
