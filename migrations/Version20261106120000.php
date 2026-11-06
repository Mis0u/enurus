<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Email announcing a new message from the admin: on by default, existing accounts included.
 */
final class Version20261106120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add users.email_on_admin_message.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD email_on_admin_message BOOLEAN DEFAULT true NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP email_on_admin_message');
    }
}
