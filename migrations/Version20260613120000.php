<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Seed des tables de référence à IDs stables (statuts et types de réservation).
 *
 * Ces valeurs sont attendues par le code (ReservationStatus::PENDING…,
 * ReservationType::STANDARD) et leurs IDs ne doivent jamais changer.
 * Idempotent (ON CONFLICT DO NOTHING) : sans effet si déjà présentes.
 */
final class Version20260613120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed reservation_statuses and reservation_types reference rows.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO reservation_statuses (id, label) VALUES
                (1, 'En attente'),
                (2, 'Confirmée'),
                (3, 'Refusée'),
                (4, 'Annulée')
            ON CONFLICT (id) DO NOTHING
        SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO reservation_types (id, label) VALUES
                (1, 'Standard')
            ON CONFLICT (id) DO NOTHING
        SQL);
    }

    public function down(Schema $schema): void
    {
        // On ne supprime que les lignes seedées ici, et seulement si aucune
        // réservation n'y fait référence (sinon la FK l'en empêchera, c'est voulu).
        $this->addSql('DELETE FROM reservation_types WHERE id = 1');
        $this->addSql("DELETE FROM reservation_statuses WHERE id IN (1, 2, 3, 4)");
    }
}
