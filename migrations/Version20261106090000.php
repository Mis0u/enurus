<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Routine archiving: deleting a routine used by workouts archives it, so they keep its name instead
 * of turning into free sessions.
 */
final class Version20261106090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add routine.archived.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE routine ADD archived BOOLEAN NOT NULL DEFAULT false');
        $this->addSql('ALTER TABLE routine ALTER COLUMN archived DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE routine DROP archived');
    }
}
