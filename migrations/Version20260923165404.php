<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Le widget heatmap du dashboard est opt-in : masqué par défaut pour les nouveaux comptes comme
 * pour les comptes existants, l'utilisateur l'active lui-même en réglages.
 */
final class Version20260923165404 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Hide the dashboard heatmap widget by default (opt-in)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ALTER hidden_widgets SET DEFAULT \'["heatmap"]\'');
        $this->addSql('UPDATE users SET hidden_widgets = (hidden_widgets::jsonb || \'["heatmap"]\'::jsonb)::json WHERE NOT jsonb_exists(hidden_widgets::jsonb, \'heatmap\')');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE users SET hidden_widgets = (hidden_widgets::jsonb - \'heatmap\')::json');
        $this->addSql('ALTER TABLE users ALTER hidden_widgets SET DEFAULT \'[]\'');
    }
}
