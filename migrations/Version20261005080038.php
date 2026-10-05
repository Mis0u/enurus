<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Friend invitation: the inviter whose link was used to sign up, stored at sign-up because the
 * connection is only created once the email is confirmed. Cleared if the inviter deletes their account.
 */
final class Version20261005080038 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add users.invited_by_id.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD invited_by_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD CONSTRAINT FK_1483A5E9A7B4A7E3 FOREIGN KEY (invited_by_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_1483A5E9A7B4A7E3 ON users (invited_by_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP CONSTRAINT FK_1483A5E9A7B4A7E3');
        $this->addSql('DROP INDEX IDX_1483A5E9A7B4A7E3');
        $this->addSql('ALTER TABLE users DROP invited_by_id');
    }
}
