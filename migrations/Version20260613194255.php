<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260613194255 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE announcement (id SERIAL NOT NULL, message TEXT NOT NULL, start_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, end_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, priority INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE blackout_instances (id SERIAL NOT NULL, blackout_series_id INT NOT NULL, start_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, end_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_6BD6C5754389F480 ON blackout_instances (blackout_series_id)');
        $this->addSql('CREATE TABLE blackout_series (id SERIAL NOT NULL, owner_id INT NOT NULL, resource_id SMALLINT NOT NULL, date_created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, title VARCHAR(85) NOT NULL, description TEXT DEFAULT NULL, legacyid VARCHAR(16) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_88923A137E3C61F9 ON blackout_series (owner_id)');
        $this->addSql('CREATE INDEX IDX_88923A1389329D25 ON blackout_series (resource_id)');
        $this->addSql('CREATE TABLE layouts (id SERIAL NOT NULL, name VARCHAR(85) NOT NULL, timezone VARCHAR(85) NOT NULL, layout_type SMALLINT DEFAULT 0 NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE presta_inscription (id SERIAL NOT NULL, session_id INT NOT NULL, client_id INT NOT NULL, statut VARCHAR(50) NOT NULL, date_creation TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_119C5837613FECDF ON presta_inscription (session_id)');
        $this->addSql('CREATE INDEX IDX_119C583719EB6921 ON presta_inscription (client_id)');
        $this->addSql('CREATE TABLE presta_plage_horaire (id SERIAL NOT NULL, prestataire_id INT NOT NULL, jour_semaine SMALLINT NOT NULL, heure_debut TIME(0) WITHOUT TIME ZONE NOT NULL, heure_fin TIME(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_F0E48979BE3DB2B7 ON presta_plage_horaire (prestataire_id)');
        $this->addSql('CREATE TABLE presta_prestataire (id SERIAL NOT NULL, user_id INT NOT NULL, nom VARCHAR(255) NOT NULL, prenom VARCHAR(255) NOT NULL, description VARCHAR(255) DEFAULT NULL, photo VARCHAR(255) DEFAULT NULL, is_active BOOLEAN DEFAULT true NOT NULL, code_unite INT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2FAECA61A76ED395 ON presta_prestataire (user_id)');
        $this->addSql('CREATE TABLE presta_service (id SERIAL NOT NULL, prestataire_id INT NOT NULL, libelle VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, duree_minutes INT NOT NULL, type VARCHAR(25) NOT NULL, capacite_max INT NOT NULL, is_active BOOLEAN DEFAULT true NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_72003864BE3DB2B7 ON presta_service (prestataire_id)');
        $this->addSql('CREATE TABLE presta_session (id SERIAL NOT NULL, prestataire_id INT NOT NULL, service_id INT NOT NULL, date_debut TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, date_fin TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, nb_inscrits INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_43D97762BE3DB2B7 ON presta_session (prestataire_id)');
        $this->addSql('CREATE INDEX IDX_43D97762ED5CA9E6 ON presta_session (service_id)');
        $this->addSql('CREATE TABLE reservation_attachment (id SERIAL NOT NULL, series_id INT DEFAULT NULL, filename VARCHAR(255) NOT NULL, original_name VARCHAR(255) NOT NULL, mime_type VARCHAR(100) DEFAULT NULL, size INT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_19F5100F5278319C ON reservation_attachment (series_id)');
        $this->addSql('CREATE TABLE reservation_audit_logs (id SERIAL NOT NULL, series_id INT DEFAULT NULL, actor_id INT DEFAULT NULL, action VARCHAR(32) NOT NULL, from_status_id SMALLINT DEFAULT NULL, to_status_id SMALLINT DEFAULT NULL, reason TEXT DEFAULT NULL, payload JSON DEFAULT NULL, occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_B685E1EC10DAF24A ON reservation_audit_logs (actor_id)');
        $this->addSql('CREATE INDEX idx_ral_series ON reservation_audit_logs (series_id)');
        $this->addSql('CREATE INDEX idx_ral_occurred_at ON reservation_audit_logs (occurred_at)');
        $this->addSql('COMMENT ON COLUMN reservation_audit_logs.occurred_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE reservation_instances (id SERIAL NOT NULL, series_id INT NOT NULL, start_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, end_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, reference_number VARCHAR(50) NOT NULL, checkin_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, checkout_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, previous_end_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, reminder_sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_606061F65278319C ON reservation_instances (series_id)');
        $this->addSql('CREATE INDEX idx_reference_number ON reservation_instances (reference_number)');
        $this->addSql('COMMENT ON COLUMN reservation_instances.reminder_sent_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE reservation_resources (series_id INT NOT NULL, resource_id SMALLINT NOT NULL, resource_level_id SMALLINT NOT NULL, PRIMARY KEY(series_id, resource_id))');
        $this->addSql('CREATE INDEX IDX_F5218A315278319C ON reservation_resources (series_id)');
        $this->addSql('CREATE INDEX IDX_F5218A3189329D25 ON reservation_resources (resource_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_series_resource ON reservation_resources (series_id, resource_id)');
        $this->addSql('CREATE TABLE reservation_series (id SERIAL NOT NULL, owner_id INT NOT NULL, type_id SMALLINT NOT NULL, status_id SMALLINT NOT NULL, uuid UUID NOT NULL, title VARCHAR(85) NOT NULL, description TEXT DEFAULT NULL, legacyid VARCHAR(16) DEFAULT NULL, allow_participation BOOLEAN DEFAULT false NOT NULL, allow_anon_participation BOOLEAN DEFAULT false NOT NULL, repeat_type VARCHAR(10) DEFAULT NULL, repeat_options VARCHAR(255) DEFAULT NULL, date_created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_72E148B6D17F50A6 ON reservation_series (uuid)');
        $this->addSql('CREATE INDEX IDX_72E148B67E3C61F9 ON reservation_series (owner_id)');
        $this->addSql('CREATE INDEX IDX_72E148B6C54C8C93 ON reservation_series (type_id)');
        $this->addSql('CREATE INDEX IDX_72E148B66BF700BD ON reservation_series (status_id)');
        $this->addSql('COMMENT ON COLUMN reservation_series.uuid IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN reservation_series.date_created IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN reservation_series.last_modified IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE reservation_statuses (id SMALLINT NOT NULL, label VARCHAR(85) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE reservation_types (id SMALLINT NOT NULL, label VARCHAR(85) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE reservation_users (reservation_instance_id INT NOT NULL, user_id INT NOT NULL, reservation_user_level SMALLINT NOT NULL, PRIMARY KEY(reservation_instance_id, user_id))');
        $this->addSql('CREATE INDEX IDX_57FC754EF63DAF41 ON reservation_users (reservation_instance_id)');
        $this->addSql('CREATE INDEX IDX_57FC754EA76ED395 ON reservation_users (user_id)');
        $this->addSql('CREATE TABLE resource_category (id SERIAL NOT NULL, name VARCHAR(100) NOT NULL, description TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE resource_group (id SERIAL NOT NULL, name VARCHAR(100) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE resource_group_user (resource_group_id INT NOT NULL, user_id INT NOT NULL, PRIMARY KEY(resource_group_id, user_id))');
        $this->addSql('CREATE INDEX IDX_C4D1CABF50D813EA ON resource_group_user (resource_group_id)');
        $this->addSql('CREATE INDEX IDX_C4D1CABFA76ED395 ON resource_group_user (user_id)');
        $this->addSql('CREATE TABLE resources (id SMALLSERIAL NOT NULL, schedule_id SMALLINT NOT NULL, category_id INT DEFAULT NULL, admin_group_id INT DEFAULT NULL, name VARCHAR(85) NOT NULL, isactive BOOLEAN DEFAULT true NOT NULL, requires_approval BOOLEAN DEFAULT false NOT NULL, allow_multiday_reservations BOOLEAN DEFAULT false NOT NULL, unit_cost NUMERIC(7, 2) DEFAULT NULL, min_duration INT DEFAULT NULL, max_duration INT DEFAULT NULL, location VARCHAR(255) DEFAULT NULL, contact_info VARCHAR(255) DEFAULT NULL, description TEXT DEFAULT NULL, notes TEXT DEFAULT NULL, min_increment INT DEFAULT NULL, autoassign BOOLEAN DEFAULT true NOT NULL, max_participants INT DEFAULT NULL, min_notice_time_add INT DEFAULT NULL, max_notice_time INT DEFAULT NULL, image_name VARCHAR(255) DEFAULT NULL, legacyid VARCHAR(16) DEFAULT NULL, public_id VARCHAR(20) DEFAULT NULL, allow_calendar_subscription BOOLEAN DEFAULT false NOT NULL, sort_order INT DEFAULT NULL, status_id INT DEFAULT 1 NOT NULL, buffer_time INT DEFAULT NULL, enable_check_in BOOLEAN DEFAULT false NOT NULL, auto_release_minutes INT DEFAULT NULL, color VARCHAR(10) DEFAULT NULL, allow_display BOOLEAN DEFAULT false NOT NULL, credit_count NUMERIC(7, 2) DEFAULT NULL, peak_credit_count NUMERIC(7, 2) DEFAULT NULL, min_notice_time_update INT DEFAULT NULL, min_notice_time_delete INT DEFAULT NULL, date_created TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, additional_properties TEXT DEFAULT NULL, resource_type_id INT DEFAULT NULL, resource_status_reason_id INT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EF66EBAEB5B48B91 ON resources (public_id)');
        $this->addSql('CREATE INDEX IDX_EF66EBAEA40BC2D5 ON resources (schedule_id)');
        $this->addSql('CREATE INDEX IDX_EF66EBAE12469DE2 ON resources (category_id)');
        $this->addSql('CREATE INDEX IDX_EF66EBAE6AF4DE41 ON resources (admin_group_id)');
        $this->addSql('CREATE TABLE schedules (id SMALLSERIAL NOT NULL, layout_id INT DEFAULT NULL, name VARCHAR(85) NOT NULL, isdefault BOOLEAN DEFAULT false NOT NULL, weekdaystart SMALLINT NOT NULL, daysvisible SMALLINT NOT NULL, notes TEXT DEFAULT NULL, published BOOLEAN DEFAULT false NOT NULL, public_id VARCHAR(50) DEFAULT NULL, allow_calendar_subscription BOOLEAN DEFAULT false NOT NULL, start_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, end_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, allow_concurrent_bookings BOOLEAN DEFAULT false NOT NULL, default_layout BOOLEAN DEFAULT false NOT NULL, total_concurrent_reservations SMALLINT DEFAULT NULL, max_resources_per_reservation SMALLINT DEFAULT NULL, additional_properties JSON DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_313BDC8EB5B48B91 ON schedules (public_id)');
        $this->addSql('CREATE INDEX IDX_313BDC8E8C22AA1A ON schedules (layout_id)');
        $this->addSql('COMMENT ON COLUMN schedules.start_date IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN schedules.end_date IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE time_blocks (id SERIAL NOT NULL, layout_id INT NOT NULL, label VARCHAR(85) DEFAULT NULL, end_label VARCHAR(85) DEFAULT NULL, day_of_week SMALLINT DEFAULT NULL, availability_code SMALLINT NOT NULL, start_time TIME(0) WITHOUT TIME ZONE NOT NULL, end_time TIME(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_tb_layout ON time_blocks (layout_id)');
        $this->addSql('CREATE INDEX idx_tb_layout_dow ON time_blocks (layout_id, day_of_week)');
        $this->addSql('COMMENT ON COLUMN time_blocks.start_time IS \'(DC2Type:time_immutable)\'');
        $this->addSql('COMMENT ON COLUMN time_blocks.end_time IS \'(DC2Type:time_immutable)\'');
        $this->addSql('CREATE TABLE user_statuses (status_id SMALLSERIAL NOT NULL, label VARCHAR(85) NOT NULL, PRIMARY KEY(status_id))');
        $this->addSql('CREATE TABLE users (id SERIAL NOT NULL, fname VARCHAR(85) DEFAULT NULL, lname VARCHAR(85) DEFAULT NULL, username VARCHAR(85) DEFAULT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, salt VARCHAR(85) DEFAULT NULL, organization VARCHAR(85) DEFAULT NULL, position VARCHAR(85) DEFAULT NULL, phone VARCHAR(85) DEFAULT NULL, timezone VARCHAR(85) NOT NULL, language VARCHAR(10) NOT NULL, homepageid SMALLINT DEFAULT 1 NOT NULL, date_created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, lastlogin TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, status_id SMALLINT NOT NULL, legacyid VARCHAR(16) DEFAULT NULL, legacypassword VARCHAR(32) DEFAULT NULL, uid VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9F85E0677 ON users (username)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL ON users (email)');
        $this->addSql('CREATE TABLE waitlist_requests (id SERIAL NOT NULL, user_id INT NOT NULL, resource_id SMALLINT NOT NULL, start_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, end_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(16) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, notified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_D7F392F2A76ED395 ON waitlist_requests (user_id)');
        $this->addSql('CREATE INDEX IDX_D7F392F289329D25 ON waitlist_requests (resource_id)');
        $this->addSql('CREATE INDEX idx_waitlist_resource_window ON waitlist_requests (resource_id, start_date, end_date)');
        $this->addSql('CREATE INDEX idx_waitlist_status ON waitlist_requests (status)');
        $this->addSql('COMMENT ON COLUMN waitlist_requests.start_date IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN waitlist_requests.end_date IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN waitlist_requests.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN waitlist_requests.notified_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE messenger_messages (id BIGSERIAL NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
        $this->addSql('COMMENT ON COLUMN messenger_messages.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.available_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.delivered_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE blackout_instances ADD CONSTRAINT FK_6BD6C5754389F480 FOREIGN KEY (blackout_series_id) REFERENCES blackout_series (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE blackout_series ADD CONSTRAINT FK_88923A137E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE blackout_series ADD CONSTRAINT FK_88923A1389329D25 FOREIGN KEY (resource_id) REFERENCES resources (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE presta_inscription ADD CONSTRAINT FK_119C5837613FECDF FOREIGN KEY (session_id) REFERENCES presta_session (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE presta_inscription ADD CONSTRAINT FK_119C583719EB6921 FOREIGN KEY (client_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE presta_plage_horaire ADD CONSTRAINT FK_F0E48979BE3DB2B7 FOREIGN KEY (prestataire_id) REFERENCES presta_prestataire (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE presta_prestataire ADD CONSTRAINT FK_2FAECA61A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE presta_service ADD CONSTRAINT FK_72003864BE3DB2B7 FOREIGN KEY (prestataire_id) REFERENCES presta_prestataire (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE presta_session ADD CONSTRAINT FK_43D97762BE3DB2B7 FOREIGN KEY (prestataire_id) REFERENCES presta_prestataire (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE presta_session ADD CONSTRAINT FK_43D97762ED5CA9E6 FOREIGN KEY (service_id) REFERENCES presta_service (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE reservation_attachment ADD CONSTRAINT FK_19F5100F5278319C FOREIGN KEY (series_id) REFERENCES reservation_series (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE reservation_audit_logs ADD CONSTRAINT FK_B685E1EC5278319C FOREIGN KEY (series_id) REFERENCES reservation_series (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE reservation_audit_logs ADD CONSTRAINT FK_B685E1EC10DAF24A FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE reservation_instances ADD CONSTRAINT FK_606061F65278319C FOREIGN KEY (series_id) REFERENCES reservation_series (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE reservation_resources ADD CONSTRAINT FK_F5218A315278319C FOREIGN KEY (series_id) REFERENCES reservation_series (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE reservation_resources ADD CONSTRAINT FK_F5218A3189329D25 FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE reservation_series ADD CONSTRAINT FK_72E148B67E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE reservation_series ADD CONSTRAINT FK_72E148B6C54C8C93 FOREIGN KEY (type_id) REFERENCES reservation_types (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE reservation_series ADD CONSTRAINT FK_72E148B66BF700BD FOREIGN KEY (status_id) REFERENCES reservation_statuses (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE reservation_users ADD CONSTRAINT FK_57FC754EF63DAF41 FOREIGN KEY (reservation_instance_id) REFERENCES reservation_instances (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE reservation_users ADD CONSTRAINT FK_57FC754EA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE resource_group_user ADD CONSTRAINT FK_C4D1CABF50D813EA FOREIGN KEY (resource_group_id) REFERENCES resource_group (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE resource_group_user ADD CONSTRAINT FK_C4D1CABFA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE resources ADD CONSTRAINT FK_EF66EBAEA40BC2D5 FOREIGN KEY (schedule_id) REFERENCES schedules (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE resources ADD CONSTRAINT FK_EF66EBAE12469DE2 FOREIGN KEY (category_id) REFERENCES resource_category (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE resources ADD CONSTRAINT FK_EF66EBAE6AF4DE41 FOREIGN KEY (admin_group_id) REFERENCES resource_group (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE schedules ADD CONSTRAINT FK_313BDC8E8C22AA1A FOREIGN KEY (layout_id) REFERENCES layouts (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE time_blocks ADD CONSTRAINT FK_971BDA8E8C22AA1A FOREIGN KEY (layout_id) REFERENCES layouts (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE waitlist_requests ADD CONSTRAINT FK_D7F392F2A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE waitlist_requests ADD CONSTRAINT FK_D7F392F289329D25 FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE blackout_instances DROP CONSTRAINT FK_6BD6C5754389F480');
        $this->addSql('ALTER TABLE blackout_series DROP CONSTRAINT FK_88923A137E3C61F9');
        $this->addSql('ALTER TABLE blackout_series DROP CONSTRAINT FK_88923A1389329D25');
        $this->addSql('ALTER TABLE presta_inscription DROP CONSTRAINT FK_119C5837613FECDF');
        $this->addSql('ALTER TABLE presta_inscription DROP CONSTRAINT FK_119C583719EB6921');
        $this->addSql('ALTER TABLE presta_plage_horaire DROP CONSTRAINT FK_F0E48979BE3DB2B7');
        $this->addSql('ALTER TABLE presta_prestataire DROP CONSTRAINT FK_2FAECA61A76ED395');
        $this->addSql('ALTER TABLE presta_service DROP CONSTRAINT FK_72003864BE3DB2B7');
        $this->addSql('ALTER TABLE presta_session DROP CONSTRAINT FK_43D97762BE3DB2B7');
        $this->addSql('ALTER TABLE presta_session DROP CONSTRAINT FK_43D97762ED5CA9E6');
        $this->addSql('ALTER TABLE reservation_attachment DROP CONSTRAINT FK_19F5100F5278319C');
        $this->addSql('ALTER TABLE reservation_audit_logs DROP CONSTRAINT FK_B685E1EC5278319C');
        $this->addSql('ALTER TABLE reservation_audit_logs DROP CONSTRAINT FK_B685E1EC10DAF24A');
        $this->addSql('ALTER TABLE reservation_instances DROP CONSTRAINT FK_606061F65278319C');
        $this->addSql('ALTER TABLE reservation_resources DROP CONSTRAINT FK_F5218A315278319C');
        $this->addSql('ALTER TABLE reservation_resources DROP CONSTRAINT FK_F5218A3189329D25');
        $this->addSql('ALTER TABLE reservation_series DROP CONSTRAINT FK_72E148B67E3C61F9');
        $this->addSql('ALTER TABLE reservation_series DROP CONSTRAINT FK_72E148B6C54C8C93');
        $this->addSql('ALTER TABLE reservation_series DROP CONSTRAINT FK_72E148B66BF700BD');
        $this->addSql('ALTER TABLE reservation_users DROP CONSTRAINT FK_57FC754EF63DAF41');
        $this->addSql('ALTER TABLE reservation_users DROP CONSTRAINT FK_57FC754EA76ED395');
        $this->addSql('ALTER TABLE resource_group_user DROP CONSTRAINT FK_C4D1CABF50D813EA');
        $this->addSql('ALTER TABLE resource_group_user DROP CONSTRAINT FK_C4D1CABFA76ED395');
        $this->addSql('ALTER TABLE resources DROP CONSTRAINT FK_EF66EBAEA40BC2D5');
        $this->addSql('ALTER TABLE resources DROP CONSTRAINT FK_EF66EBAE12469DE2');
        $this->addSql('ALTER TABLE resources DROP CONSTRAINT FK_EF66EBAE6AF4DE41');
        $this->addSql('ALTER TABLE schedules DROP CONSTRAINT FK_313BDC8E8C22AA1A');
        $this->addSql('ALTER TABLE time_blocks DROP CONSTRAINT FK_971BDA8E8C22AA1A');
        $this->addSql('ALTER TABLE waitlist_requests DROP CONSTRAINT FK_D7F392F2A76ED395');
        $this->addSql('ALTER TABLE waitlist_requests DROP CONSTRAINT FK_D7F392F289329D25');
        $this->addSql('DROP TABLE announcement');
        $this->addSql('DROP TABLE blackout_instances');
        $this->addSql('DROP TABLE blackout_series');
        $this->addSql('DROP TABLE layouts');
        $this->addSql('DROP TABLE presta_inscription');
        $this->addSql('DROP TABLE presta_plage_horaire');
        $this->addSql('DROP TABLE presta_prestataire');
        $this->addSql('DROP TABLE presta_service');
        $this->addSql('DROP TABLE presta_session');
        $this->addSql('DROP TABLE reservation_attachment');
        $this->addSql('DROP TABLE reservation_audit_logs');
        $this->addSql('DROP TABLE reservation_instances');
        $this->addSql('DROP TABLE reservation_resources');
        $this->addSql('DROP TABLE reservation_series');
        $this->addSql('DROP TABLE reservation_statuses');
        $this->addSql('DROP TABLE reservation_types');
        $this->addSql('DROP TABLE reservation_users');
        $this->addSql('DROP TABLE resource_category');
        $this->addSql('DROP TABLE resource_group');
        $this->addSql('DROP TABLE resource_group_user');
        $this->addSql('DROP TABLE resources');
        $this->addSql('DROP TABLE schedules');
        $this->addSql('DROP TABLE time_blocks');
        $this->addSql('DROP TABLE user_statuses');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE waitlist_requests');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
