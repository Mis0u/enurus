<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260807133428 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ContactThread.isWelcomeMessage to exclude registration welcome threads from the admin thread list';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contact_thread ADD is_welcome_message BOOLEAN NOT NULL DEFAULT false');
        $this->addSql('ALTER TABLE contact_thread ALTER COLUMN is_welcome_message DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contact_thread DROP is_welcome_message');
    }
}
