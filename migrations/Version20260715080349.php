<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260715080349 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add hidden_by_user_at to contact_thread (per-user soft delete)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contact_thread ADD hidden_by_user_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE users ALTER contact_restricted_permanently DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contact_thread DROP hidden_by_user_at');
        $this->addSql('ALTER TABLE users ALTER contact_restricted_permanently SET DEFAULT false');
    }
}
