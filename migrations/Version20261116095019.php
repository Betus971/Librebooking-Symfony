<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rétablit reservation_instances.reminder_sent_at sur les bases où l'ancienne
 * migration Version20260807191520 (avant correction) l'avait supprimée.
 * Idempotent (IF NOT EXISTS) : la colonne existe déjà sur les bases saines.
 */
final class Version20261116095019 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rétablit reservation_instances.reminder_sent_at (rappels J-1) si absente.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation_instances ADD COLUMN IF NOT EXISTS reminder_sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN reservation_instances.reminder_sent_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation_instances DROP COLUMN IF EXISTS reminder_sent_at');
    }
}
