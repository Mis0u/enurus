<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ordre des widgets du dashboard choisi en réglages — vide = ordre par défaut, aucun changement
 * pour les comptes existants.
 */
final class Version20260928105604 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the dashboard widget order to users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users ADD widget_order JSON DEFAULT '[]' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP widget_order');
    }
}
