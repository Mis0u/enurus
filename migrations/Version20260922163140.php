<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260922163140 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add deload_period table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE deload_period (id UUID NOT NULL, start_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, end_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, note TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, owner_id UUID NOT NULL, created_by_id UUID DEFAULT NULL, updated_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_B5B3ABC57E3C61F9 ON deload_period (owner_id)');
        $this->addSql('CREATE INDEX IDX_B5B3ABC5B03A8386 ON deload_period (created_by_id)');
        $this->addSql('CREATE INDEX IDX_B5B3ABC5896DBBDE ON deload_period (updated_by_id)');
        $this->addSql('ALTER TABLE deload_period ADD CONSTRAINT FK_B5B3ABC57E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE deload_period ADD CONSTRAINT FK_B5B3ABC5B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE deload_period ADD CONSTRAINT FK_B5B3ABC5896DBBDE FOREIGN KEY (updated_by_id) REFERENCES users (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE deload_period DROP CONSTRAINT FK_B5B3ABC57E3C61F9');
        $this->addSql('ALTER TABLE deload_period DROP CONSTRAINT FK_B5B3ABC5B03A8386');
        $this->addSql('ALTER TABLE deload_period DROP CONSTRAINT FK_B5B3ABC5896DBBDE');
        $this->addSql('DROP TABLE deload_period');
    }
}
