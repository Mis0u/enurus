<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Seed the single ContactNotificationSetting row (global Telegram notification toggle, admin-editable).
 */
final class Version20260806120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed the single ContactNotificationSetting row (global Telegram notification toggle, admin-editable).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE contact_notification_setting (id UUID NOT NULL, telegram_notifications_enabled BOOLEAN NOT NULL, PRIMARY KEY (id))');
        $this->addSql("INSERT INTO contact_notification_setting (id, telegram_notifications_enabled) VALUES ('0198f5b2-0001-7000-8000-000000000000', true)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE contact_notification_setting');
    }
}
