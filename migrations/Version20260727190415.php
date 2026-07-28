<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260727190415 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add User::isVerified, backfilled to true for existing accounts (mandatory email verification only applies to new registrations, cf. TODO #24)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD is_verified BOOLEAN DEFAULT TRUE NOT NULL');
        $this->addSql('ALTER TABLE users ALTER COLUMN is_verified DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE users DROP is_verified');
    }
}
