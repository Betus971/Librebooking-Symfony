<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Dé-gendarmerisation : suppression du scoping par "code unité".
 * Retire la colonne presta_prestataire.code_unite (devenue inutilisée).
 */
final class Version20260817140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Retire la colonne code_unite (scoping par unité, spécifique gendarmerie).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE presta_prestataire DROP COLUMN IF EXISTS code_unite');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE presta_prestataire ADD code_unite INT DEFAULT NULL');
    }
}
