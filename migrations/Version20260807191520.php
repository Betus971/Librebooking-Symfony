<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260807191520 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE accessoire (id SERIAL NOT NULL, nom VARCHAR(100) NOT NULL, quantite_disponible INT DEFAULT NULL, description TEXT DEFAULT NULL, actif BOOLEAN DEFAULT true NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8FD026A6C6E55B5 ON accessoire (nom)');
        $this->addSql('CREATE TABLE accessoire_resource (accessoire_id INT NOT NULL, resource_id SMALLINT NOT NULL, PRIMARY KEY(accessoire_id, resource_id))');
        $this->addSql('CREATE INDEX IDX_F0C36445D23B67ED ON accessoire_resource (accessoire_id)');
        $this->addSql('CREATE INDEX IDX_F0C3644589329D25 ON accessoire_resource (resource_id)');
        $this->addSql('CREATE TABLE equipement (id SERIAL NOT NULL, nom VARCHAR(80) NOT NULL, actif BOOLEAN DEFAULT true NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B8B4C6F36C6E55B5 ON equipement (nom)');
        $this->addSql('CREATE TABLE presta_absence (id SERIAL NOT NULL, prestataire_id INT NOT NULL, date_debut TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, date_fin TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, motif VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_E5C7427FBE3DB2B7 ON presta_absence (prestataire_id)');
        $this->addSql('CREATE TABLE presta_categorie (id SERIAL NOT NULL, nom VARCHAR(100) NOT NULL, description TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE reservation_accessoire (id SERIAL NOT NULL, series_id INT NOT NULL, accessoire_id INT NOT NULL, quantite_demandee INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_6857CBDE5278319C ON reservation_accessoire (series_id)');
        $this->addSql('CREATE INDEX IDX_6857CBDED23B67ED ON reservation_accessoire (accessoire_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_resa_accessoire ON reservation_accessoire (series_id, accessoire_id)');
        $this->addSql('CREATE TABLE resource_group_admin (resource_group_id INT NOT NULL, user_id INT NOT NULL, PRIMARY KEY(resource_group_id, user_id))');
        $this->addSql('CREATE INDEX IDX_DC99184350D813EA ON resource_group_admin (resource_group_id)');
        $this->addSql('CREATE INDEX IDX_DC991843A76ED395 ON resource_group_admin (user_id)');
        $this->addSql('CREATE TABLE resource_equipement (resource_id SMALLINT NOT NULL, equipement_id INT NOT NULL, PRIMARY KEY(resource_id, equipement_id))');
        $this->addSql('CREATE INDEX IDX_D8AC817589329D25 ON resource_equipement (resource_id)');
        $this->addSql('CREATE INDEX IDX_D8AC8175806F0F5C ON resource_equipement (equipement_id)');
        $this->addSql('ALTER TABLE accessoire_resource ADD CONSTRAINT FK_F0C36445D23B67ED FOREIGN KEY (accessoire_id) REFERENCES accessoire (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE accessoire_resource ADD CONSTRAINT FK_F0C3644589329D25 FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE presta_absence ADD CONSTRAINT FK_E5C7427FBE3DB2B7 FOREIGN KEY (prestataire_id) REFERENCES presta_prestataire (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE reservation_accessoire ADD CONSTRAINT FK_6857CBDE5278319C FOREIGN KEY (series_id) REFERENCES reservation_series (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE reservation_accessoire ADD CONSTRAINT FK_6857CBDED23B67ED FOREIGN KEY (accessoire_id) REFERENCES accessoire (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE resource_group_admin ADD CONSTRAINT FK_DC99184350D813EA FOREIGN KEY (resource_group_id) REFERENCES resource_group (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE resource_group_admin ADD CONSTRAINT FK_DC991843A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE resource_equipement ADD CONSTRAINT FK_D8AC817589329D25 FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE resource_equipement ADD CONSTRAINT FK_D8AC8175806F0F5C FOREIGN KEY (equipement_id) REFERENCES equipement (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE presta_plage_horaire ADD date_debut DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE presta_plage_horaire ADD date_fin DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE presta_prestataire ADD horizon_jours INT DEFAULT 14 NOT NULL');
        $this->addSql('ALTER TABLE presta_prestataire ADD delai_annulation_heures INT DEFAULT 48 NOT NULL');
        $this->addSql('ALTER TABLE presta_prestataire ADD un_rdv_actif_par_client BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE presta_prestataire ADD ical_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE presta_service ADD categorie_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE presta_service ADD requires_approval BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE presta_service ADD couleur VARCHAR(7) DEFAULT NULL');
        $this->addSql('ALTER TABLE presta_service ADD CONSTRAINT FK_72003864BCF5E72D FOREIGN KEY (categorie_id) REFERENCES presta_categorie (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_72003864BCF5E72D ON presta_service (categorie_id)');
        $this->addSql('ALTER TABLE presta_session ADD client_nom VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE presta_session ADD note TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE reservation_instances DROP reminder_sent_at');
        $this->addSql('CREATE INDEX idx_start_date ON reservation_instances (start_date)');
        $this->addSql('CREATE INDEX idx_end_date ON reservation_instances (end_date)');
        $this->addSql('ALTER TABLE reservation_series ADD nombre_participants INT DEFAULT NULL');
        $this->addSql('ALTER TABLE resources DROP CONSTRAINT FK_EF66EBAE12469DE2');
        $this->addSql('ALTER TABLE resources ADD requires_participants BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE resources ADD CONSTRAINT FK_EF66EBAE12469DE2 FOREIGN KEY (category_id) REFERENCES resource_category (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE presta_service DROP CONSTRAINT FK_72003864BCF5E72D');
        $this->addSql('ALTER TABLE accessoire_resource DROP CONSTRAINT FK_F0C36445D23B67ED');
        $this->addSql('ALTER TABLE accessoire_resource DROP CONSTRAINT FK_F0C3644589329D25');
        $this->addSql('ALTER TABLE presta_absence DROP CONSTRAINT FK_E5C7427FBE3DB2B7');
        $this->addSql('ALTER TABLE reservation_accessoire DROP CONSTRAINT FK_6857CBDE5278319C');
        $this->addSql('ALTER TABLE reservation_accessoire DROP CONSTRAINT FK_6857CBDED23B67ED');
        $this->addSql('ALTER TABLE resource_group_admin DROP CONSTRAINT FK_DC99184350D813EA');
        $this->addSql('ALTER TABLE resource_group_admin DROP CONSTRAINT FK_DC991843A76ED395');
        $this->addSql('ALTER TABLE resource_equipement DROP CONSTRAINT FK_D8AC817589329D25');
        $this->addSql('ALTER TABLE resource_equipement DROP CONSTRAINT FK_D8AC8175806F0F5C');
        $this->addSql('DROP TABLE accessoire');
        $this->addSql('DROP TABLE accessoire_resource');
        $this->addSql('DROP TABLE equipement');
        $this->addSql('DROP TABLE presta_absence');
        $this->addSql('DROP TABLE presta_categorie');
        $this->addSql('DROP TABLE reservation_accessoire');
        $this->addSql('DROP TABLE resource_group_admin');
        $this->addSql('DROP TABLE resource_equipement');
        $this->addSql('ALTER TABLE reservation_series DROP nombre_participants');
        $this->addSql('ALTER TABLE resources DROP CONSTRAINT fk_ef66ebae12469de2');
        $this->addSql('ALTER TABLE resources DROP requires_participants');
        $this->addSql('ALTER TABLE resources ADD CONSTRAINT fk_ef66ebae12469de2 FOREIGN KEY (category_id) REFERENCES resource_category (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE presta_prestataire DROP horizon_jours');
        $this->addSql('ALTER TABLE presta_prestataire DROP delai_annulation_heures');
        $this->addSql('ALTER TABLE presta_prestataire DROP un_rdv_actif_par_client');
        $this->addSql('ALTER TABLE presta_prestataire DROP ical_token');
        $this->addSql('ALTER TABLE presta_plage_horaire DROP date_debut');
        $this->addSql('ALTER TABLE presta_plage_horaire DROP date_fin');
        $this->addSql('DROP INDEX IDX_72003864BCF5E72D');
        $this->addSql('ALTER TABLE presta_service DROP categorie_id');
        $this->addSql('ALTER TABLE presta_service DROP requires_approval');
        $this->addSql('ALTER TABLE presta_service DROP couleur');
        $this->addSql('DROP INDEX idx_start_date');
        $this->addSql('DROP INDEX idx_end_date');
        $this->addSql('ALTER TABLE reservation_instances ADD reminder_sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN reservation_instances.reminder_sent_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE presta_session DROP client_nom');
        $this->addSql('ALTER TABLE presta_session DROP note');
    }
}
