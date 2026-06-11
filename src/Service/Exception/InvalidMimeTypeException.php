<?php

namespace App\Service\Exception;

/**
 * Levée par {@see \App\Service\FileUploadService} quand le type MIME réel
 * (détecté via finfo) d'un fichier uploadé n'est pas dans la liste autorisée.
 */
final class InvalidMimeTypeException extends \RuntimeException
{
    public function __construct(private readonly string $mimeType)
    {
        parent::__construct(sprintf('Type MIME non autorisé : %s', $mimeType));
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }
}
