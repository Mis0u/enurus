<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260812090457 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ExerciseSet::distance (exercices mesurés par distance, ex. farmer walk)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE exercise_set ADD distance INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE exercise_set DROP distance');
    }
}
