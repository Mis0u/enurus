<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Seed the single FeatureSetting row (admin-editable feature switches). Workout photo upload
 * starts disabled: nobody used it, the code stays in place to be switched back on later.
 */
final class Version20260929120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed the single FeatureSetting row (admin-editable feature switches, workout photo upload disabled).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE feature_setting (id UUID NOT NULL, workout_photo_upload_enabled BOOLEAN NOT NULL, PRIMARY KEY (id))');
        $this->addSql("INSERT INTO feature_setting (id, workout_photo_upload_enabled) VALUES ('0199945a-0001-7000-8000-000000000000', false)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE feature_setting');
    }
}
