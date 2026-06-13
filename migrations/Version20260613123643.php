<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260613123643 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE waitlist_requests (id SERIAL NOT NULL, user_id INT NOT NULL, resource_id SMALLINT NOT NULL, start_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, end_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(16) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, notified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_D7F392F2A76ED395 ON waitlist_requests (user_id)');
        $this->addSql('CREATE INDEX IDX_D7F392F289329D25 ON waitlist_requests (resource_id)');
        $this->addSql('CREATE INDEX idx_waitlist_resource_window ON waitlist_requests (resource_id, start_date, end_date)');
        $this->addSql('CREATE INDEX idx_waitlist_status ON waitlist_requests (status)');
        $this->addSql('COMMENT ON COLUMN waitlist_requests.start_date IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN waitlist_requests.end_date IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN waitlist_requests.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN waitlist_requests.notified_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE waitlist_requests ADD CONSTRAINT FK_D7F392F2A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE waitlist_requests ADD CONSTRAINT FK_D7F392F289329D25 FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE reservation_instances ADD reminder_sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN reservation_instances.reminder_sent_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE waitlist_requests DROP CONSTRAINT FK_D7F392F2A76ED395');
        $this->addSql('ALTER TABLE waitlist_requests DROP CONSTRAINT FK_D7F392F289329D25');
        $this->addSql('DROP TABLE waitlist_requests');
        $this->addSql('ALTER TABLE reservation_instances DROP reminder_sent_at');
    }
}
