<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Deleted account trace: keep the email itself, shown in the admin. Traces recorded before this
 * migration only have the hash and stay without email until they are purged.
 */
final class Version20261022080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add deleted_account_trace.email.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE deleted_account_trace ADD email VARCHAR(180) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE deleted_account_trace DROP email');
    }
}
