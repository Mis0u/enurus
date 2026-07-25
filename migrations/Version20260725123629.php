<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260725123629 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add poll (vote) support to contact broadcasts: category, pollClosesAt, ContactPollOption, ContactPollVote';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE contact_poll_option (id UUID NOT NULL, label VARCHAR(150) NOT NULL, position INT NOT NULL, broadcast_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_D82FFC559C7E80E0 ON contact_poll_option (broadcast_id)');
        $this->addSql('CREATE TABLE contact_poll_vote (id UUID NOT NULL, voted_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, thread_id UUID NOT NULL, option_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_83A7ADC5E2904019 ON contact_poll_vote (thread_id)');
        $this->addSql('CREATE INDEX IDX_83A7ADC5A7C41D6F ON contact_poll_vote (option_id)');
        $this->addSql('ALTER TABLE contact_poll_option ADD CONSTRAINT FK_D82FFC559C7E80E0 FOREIGN KEY (broadcast_id) REFERENCES contact_broadcast (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE contact_poll_vote ADD CONSTRAINT FK_83A7ADC5E2904019 FOREIGN KEY (thread_id) REFERENCES contact_thread (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE contact_poll_vote ADD CONSTRAINT FK_83A7ADC5A7C41D6F FOREIGN KEY (option_id) REFERENCES contact_poll_option (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE contact_broadcast ADD category VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE contact_broadcast ADD poll_closes_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contact_poll_option DROP CONSTRAINT FK_D82FFC559C7E80E0');
        $this->addSql('ALTER TABLE contact_poll_vote DROP CONSTRAINT FK_83A7ADC5E2904019');
        $this->addSql('ALTER TABLE contact_poll_vote DROP CONSTRAINT FK_83A7ADC5A7C41D6F');
        $this->addSql('DROP TABLE contact_poll_option');
        $this->addSql('DROP TABLE contact_poll_vote');
        $this->addSql('ALTER TABLE contact_broadcast DROP category');
        $this->addSql('ALTER TABLE contact_broadcast DROP poll_closes_at');
    }
}
