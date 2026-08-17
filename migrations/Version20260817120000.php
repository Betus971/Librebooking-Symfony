<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Table des surcharges de réglages (app_setting) — page de configuration v2.
 */
final class Version20260817120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la table app_setting (réglages de l\'application, page de configuration v2).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE app_setting (id SERIAL NOT NULL, cle VARCHAR(100) NOT NULL, valeur TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_app_setting_cle ON app_setting (cle)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE app_setting');
    }
}
