<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260918182312 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE profile_connection (id UUID NOT NULL, status VARCHAR(20) NOT NULL, responded_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, requester_id UUID NOT NULL, addressee_id UUID NOT NULL, created_by_id UUID DEFAULT NULL, updated_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_EE9C443BED442CF4 ON profile_connection (requester_id)');
        $this->addSql('CREATE INDEX IDX_EE9C443B2261B4C3 ON profile_connection (addressee_id)');
        $this->addSql('CREATE INDEX IDX_EE9C443BB03A8386 ON profile_connection (created_by_id)');
        $this->addSql('CREATE INDEX IDX_EE9C443B896DBBDE ON profile_connection (updated_by_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PROFILE_CONNECTION_PAIR ON profile_connection (requester_id, addressee_id)');
        $this->addSql('ALTER TABLE profile_connection ADD CONSTRAINT FK_EE9C443BED442CF4 FOREIGN KEY (requester_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE profile_connection ADD CONSTRAINT FK_EE9C443B2261B4C3 FOREIGN KEY (addressee_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE profile_connection ADD CONSTRAINT FK_EE9C443BB03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE profile_connection ADD CONSTRAINT FK_EE9C443B896DBBDE FOREIGN KEY (updated_by_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE users ADD is_discoverable BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE users ADD share_code VARCHAR(6) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9CAD23D01 ON users (share_code)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE profile_connection DROP CONSTRAINT FK_EE9C443BED442CF4');
        $this->addSql('ALTER TABLE profile_connection DROP CONSTRAINT FK_EE9C443B2261B4C3');
        $this->addSql('ALTER TABLE profile_connection DROP CONSTRAINT FK_EE9C443BB03A8386');
        $this->addSql('ALTER TABLE profile_connection DROP CONSTRAINT FK_EE9C443B896DBBDE');
        $this->addSql('DROP TABLE profile_connection');
        $this->addSql('DROP INDEX UNIQ_1483A5E9CAD23D01');
        $this->addSql('ALTER TABLE users DROP is_discoverable');
        $this->addSql('ALTER TABLE users DROP share_code');
    }
}
