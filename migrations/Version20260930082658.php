<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Frozen yearly review ("Ton année"): one row per user and year, created on December 16th, plus
 * the per-user opt-out of the announcement email.
 */
final class Version20260930082658 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create year_in_review table and add users.email_on_year_in_review.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE year_in_review (id UUID NOT NULL, year SMALLINT NOT NULL, workout_count INT NOT NULL, snapshot_data JSON DEFAULT NULL, emailed_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, seen_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, banner_dismissed_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, owner_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_F34576527E3C61F9 ON year_in_review (owner_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_YEAR_IN_REVIEW_OWNER_YEAR ON year_in_review (owner_id, year)');
        $this->addSql('ALTER TABLE year_in_review ADD CONSTRAINT FK_F34576527E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE users ADD email_on_year_in_review BOOLEAN DEFAULT true NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE year_in_review DROP CONSTRAINT FK_F34576527E3C61F9');
        $this->addSql('DROP TABLE year_in_review');
        $this->addSql('ALTER TABLE users DROP email_on_year_in_review');
    }
}
