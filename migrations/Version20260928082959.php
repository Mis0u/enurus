<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Tour guidé réservé aux nouveaux comptes : les comptes existants connaissent déjà l'appli, ils
 * sont marqués comme l'ayant vu (relançable à la demande depuis la page Aide).
 */
final class Version20260928082959 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the guided tour seen flag to users, set for existing accounts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD guided_tour_seen BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('UPDATE users SET guided_tour_seen = true');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP guided_tour_seen');
    }
}
