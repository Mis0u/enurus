<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260723080303 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ContactBroadcast entity and ContactThread.broadcast FK for grouped admin sends';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE contact_broadcast (id UUID NOT NULL, subject VARCHAR(150) NOT NULL, body TEXT NOT NULL, target VARCHAR(255) NOT NULL, locale VARCHAR(255) DEFAULT NULL, recipient_count INT NOT NULL, sent_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, sent_by_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_F5005F8EA45BB98C ON contact_broadcast (sent_by_id)');
        $this->addSql('ALTER TABLE contact_broadcast ADD CONSTRAINT FK_F5005F8EA45BB98C FOREIGN KEY (sent_by_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE contact_thread ADD broadcast_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE contact_thread ADD CONSTRAINT FK_A1B27B029C7E80E0 FOREIGN KEY (broadcast_id) REFERENCES contact_broadcast (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_A1B27B029C7E80E0 ON contact_thread (broadcast_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contact_broadcast DROP CONSTRAINT FK_F5005F8EA45BB98C');
        $this->addSql('DROP TABLE contact_broadcast');
        $this->addSql('ALTER TABLE contact_thread DROP CONSTRAINT FK_A1B27B029C7E80E0');
        $this->addSql('DROP INDEX IDX_A1B27B029C7E80E0');
        $this->addSql('ALTER TABLE contact_thread DROP broadcast_id');
    }
}
