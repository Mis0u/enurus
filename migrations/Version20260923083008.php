<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260923083008 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user_badge table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_badge (id UUID NOT NULL, family VARCHAR(20) NOT NULL, tier SMALLINT NOT NULL, unlocked_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, owner_id UUID NOT NULL, workout_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_1C32B3457E3C61F9 ON user_badge (owner_id)');
        $this->addSql('CREATE INDEX IDX_1C32B345A6CCCFC9 ON user_badge (workout_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_USER_BADGE_OWNER_FAMILY_TIER ON user_badge (owner_id, family, tier)');
        $this->addSql('ALTER TABLE user_badge ADD CONSTRAINT FK_1C32B3457E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE user_badge ADD CONSTRAINT FK_1C32B345A6CCCFC9 FOREIGN KEY (workout_id) REFERENCES workout (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_badge DROP CONSTRAINT FK_1C32B3457E3C61F9');
        $this->addSql('ALTER TABLE user_badge DROP CONSTRAINT FK_1C32B345A6CCCFC9');
        $this->addSql('DROP TABLE user_badge');
    }
}
