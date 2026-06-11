<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260606192314 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalize reservation_series.last_modified to an immutable datetime and drop legacy organization-specific columns if present.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation_series ALTER last_modified TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN reservation_series.last_modified IS \'(DC2Type:datetime_immutable)\'');

        // Cleanup of legacy organization-specific columns inherited from a private overlay.
        // Guarded with IF EXISTS so this is a no-op on a fresh generic install.
        $this->addSql('DROP INDEX IF EXISTS idx_resources_code_unite');
        $this->addSql('ALTER TABLE resources DROP IF EXISTS code_unite');
        $this->addSql('ALTER TABLE users DROP IF EXISTS nigend');
        $this->addSql('ALTER TABLE users DROP IF EXISTS codeunite');
    }

    public function down(Schema $schema): void
    {
        // Only reverse the generic change performed by up(). The dropped legacy
        // columns belonged to a private overlay and are intentionally not recreated.
        $this->addSql('COMMENT ON COLUMN reservation_series.last_modified IS NULL');
    }
}
