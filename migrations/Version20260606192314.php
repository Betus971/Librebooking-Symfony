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
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservation_series ALTER last_modified TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN reservation_series.last_modified IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('DROP INDEX IF EXISTS idx_resources_code_unite');
        $this->addSql('ALTER TABLE resources DROP IF EXISTS code_unite');
        $this->addSql('ALTER TABLE users DROP IF EXISTS nigend');
        $this->addSql('ALTER TABLE users DROP IF EXISTS codeunite');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE SEQUENCE badge_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE badge_attribution_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE badge_type_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE ifpr_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE visitor_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE visitor_category_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE visit_group_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE visit_group_id_seq1 INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE visitor (id SERIAL NOT NULL, host_id INT NOT NULL, category_id INT NOT NULL, visit_group_id INT DEFAULT NULL, nom VARCHAR(50) NOT NULL, prenom VARCHAR(50) NOT NULL, date_naiss DATE NOT NULL, pays_naissance VARCHAR(2) NOT NULL, ville_naissance VARCHAR(100) DEFAULT NULL, code_departement_naissance VARCHAR(3) DEFAULT NULL, societe VARCHAR(50) DEFAULT NULL, vehicule VARCHAR(50) DEFAULT NULL, immatriculation VARCHAR(20) DEFAULT NULL, piece_identite VARCHAR(255) DEFAULT NULL, numero_identite VARCHAR(50) DEFAULT NULL, date_arrivee TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, date_depart TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, statut_securite VARCHAR(50) DEFAULT NULL, date_verification TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_cae5e19f12469de2 ON visitor (category_id)');
        $this->addSql('CREATE INDEX idx_cae5e19f1fb8d185 ON visitor (host_id)');
        $this->addSql('CREATE INDEX idx_visitor_visit_group ON visitor (visit_group_id)');
        $this->addSql('CREATE TABLE ifpr (id SERIAL NOT NULL, agent_controle_id INT DEFAULT NULL, nom VARCHAR(50) NOT NULL, prenom VARCHAR(50) NOT NULL, date_naiss DATE NOT NULL, date_ipfr DATE DEFAULT NULL, mois_validite INT NOT NULL, est_interdit BOOLEAN DEFAULT NULL, commentaire TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_ba04b9e1e5477de6 ON ifpr (agent_controle_id)');
        $this->addSql('CREATE TABLE visit_group (id SERIAL NOT NULL, host_id INT NOT NULL, reference VARCHAR(150) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_visit_group_host ON visit_group (host_id)');
        $this->addSql('COMMENT ON COLUMN visit_group.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE badge_type (id SERIAL NOT NULL, nom VARCHAR(50) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE badge (id SERIAL NOT NULL, badge_type_id INT NOT NULL, numero VARCHAR(10) NOT NULL, disponible BOOLEAN DEFAULT true NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_fef0481dc3c8852f ON badge (badge_type_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_fef0481df55ae19e ON badge (numero)');
        $this->addSql('CREATE TABLE badge_attribution (id SERIAL NOT NULL, badge_id INT NOT NULL, visitor_id INT NOT NULL, agent_attribution_id INT DEFAULT NULL, agent_restitution_id INT DEFAULT NULL, date_attribution TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, date_retour TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_29a8df9e70bee6d ON badge_attribution (visitor_id)');
        $this->addSql('CREATE INDEX idx_29a8df9e8b0a07dc ON badge_attribution (agent_restitution_id)');
        $this->addSql('CREATE INDEX idx_29a8df9ebbbf7903 ON badge_attribution (agent_attribution_id)');
        $this->addSql('CREATE INDEX idx_29a8df9ef7a2c2fc ON badge_attribution (badge_id)');
        $this->addSql('CREATE TABLE visitor_category (id SERIAL NOT NULL, nom VARCHAR(50) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE visitor ADD CONSTRAINT fk_cae5e19f12469de2 FOREIGN KEY (category_id) REFERENCES visitor_category (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE visitor ADD CONSTRAINT fk_cae5e19f1fb8d185 FOREIGN KEY (host_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE visitor ADD CONSTRAINT fk_visitor_visit_group FOREIGN KEY (visit_group_id) REFERENCES visit_group (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE ifpr ADD CONSTRAINT fk_ba04b9e1e5477de6 FOREIGN KEY (agent_controle_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE visit_group ADD CONSTRAINT fk_visit_group_host FOREIGN KEY (host_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE badge ADD CONSTRAINT fk_fef0481dc3c8852f FOREIGN KEY (badge_type_id) REFERENCES badge_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE badge_attribution ADD CONSTRAINT fk_29a8df9e70bee6d FOREIGN KEY (visitor_id) REFERENCES visitor (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE badge_attribution ADD CONSTRAINT fk_29a8df9e8b0a07dc FOREIGN KEY (agent_restitution_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE badge_attribution ADD CONSTRAINT fk_29a8df9ebbbf7903 FOREIGN KEY (agent_attribution_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE badge_attribution ADD CONSTRAINT fk_29a8df9ef7a2c2fc FOREIGN KEY (badge_id) REFERENCES badge (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE reservation_series ALTER last_modified TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN reservation_series.last_modified IS NULL');
        $this->addSql('ALTER TABLE users ADD nigend VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD codeunite INT DEFAULT NULL');
        $this->addSql('ALTER TABLE resources ADD code_unite INT DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_resources_code_unite ON resources (code_unite)');
    }
}
