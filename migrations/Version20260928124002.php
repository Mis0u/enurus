<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Moment où l'atteinte d'un objectif de régularité a été célébrée : une seule célébration par objectif.
 */
final class Version20260928124002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the celebration date to regularity goals';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE regularity_goal ADD achieved_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE regularity_goal DROP achieved_at');
    }
}
