<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260722080945 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add sessions table (DB-backed session storage, needed to invalidate active sessions on password change)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE sessions (
              id UUID NOT NULL,
              session_id VARCHAR(128) NOT NULL,
              data BYTEA NOT NULL,
              lifetime INT NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              user_id UUID DEFAULT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9A609D13613FECDF ON sessions (session_id)');
        $this->addSql('CREATE INDEX idx_sessions_user ON sessions (user_id)');
        $this->addSql('CREATE INDEX idx_sessions_updated_at ON sessions (updated_at)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              sessions
            ADD
              CONSTRAINT FK_9A609D13A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE sessions DROP CONSTRAINT FK_9A609D13A76ED395');
        $this->addSql('DROP TABLE sessions');
    }
}
