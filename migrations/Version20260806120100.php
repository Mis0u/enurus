<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add User::$telegramNotificationsMuted (per-user mute of the admin's Telegram new-message notification).
 */
final class Version20260806120100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add User::$telegramNotificationsMuted (per-user mute of the admin Telegram notification).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD telegram_notifications_muted BOOLEAN NOT NULL DEFAULT false');
        $this->addSql('ALTER TABLE users ALTER COLUMN telegram_notifications_muted DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP telegram_notifications_muted');
    }
}
