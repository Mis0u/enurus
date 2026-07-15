<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260714195257 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add contact_thread / contact_thread_message tables and contact restriction fields on users';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE contact_thread (id UUID NOT NULL, category VARCHAR(255) NOT NULL, subject VARCHAR(150) NOT NULL, status VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, closed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, owner_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_A1B27B027E3C61F9 ON contact_thread (owner_id)');
        $this->addSql('CREATE TABLE contact_thread_message (id UUID NOT NULL, from_admin BOOLEAN NOT NULL, body TEXT NOT NULL, image_path VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, read_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, thread_id UUID NOT NULL, author_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_74FA56F6E2904019 ON contact_thread_message (thread_id)');
        $this->addSql('CREATE INDEX IDX_74FA56F6F675F31B ON contact_thread_message (author_id)');
        $this->addSql('ALTER TABLE contact_thread ADD CONSTRAINT FK_A1B27B027E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE contact_thread_message ADD CONSTRAINT FK_74FA56F6E2904019 FOREIGN KEY (thread_id) REFERENCES contact_thread (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE contact_thread_message ADD CONSTRAINT FK_74FA56F6F675F31B FOREIGN KEY (author_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE users ADD contact_restricted_until TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD contact_restricted_permanently BOOLEAN NOT NULL DEFAULT false');
        $this->addSql('ALTER TABLE users ADD contact_restriction_duration VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contact_thread DROP CONSTRAINT FK_A1B27B027E3C61F9');
        $this->addSql('ALTER TABLE contact_thread_message DROP CONSTRAINT FK_74FA56F6E2904019');
        $this->addSql('ALTER TABLE contact_thread_message DROP CONSTRAINT FK_74FA56F6F675F31B');
        $this->addSql('DROP TABLE contact_thread');
        $this->addSql('DROP TABLE contact_thread_message');
        $this->addSql('ALTER TABLE users DROP contact_restricted_until');
        $this->addSql('ALTER TABLE users DROP contact_restricted_permanently');
        $this->addSql('ALTER TABLE users DROP contact_restriction_duration');
    }
}
