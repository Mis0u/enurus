<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260715201254 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ON DELETE CASCADE on contact_thread_message.thread_id and workout_exercise.workout_id for consistency with sibling parent-child relations';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contact_thread_message DROP CONSTRAINT fk_74fa56f6e2904019');
        $this->addSql('ALTER TABLE contact_thread_message ADD CONSTRAINT FK_74FA56F6E2904019 FOREIGN KEY (thread_id) REFERENCES contact_thread (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE workout_exercise DROP CONSTRAINT fk_76ab38aaa6cccfc9');
        $this->addSql('ALTER TABLE workout_exercise ADD CONSTRAINT FK_76AB38AAA6CCCFC9 FOREIGN KEY (workout_id) REFERENCES workout (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contact_thread_message DROP CONSTRAINT FK_74FA56F6E2904019');
        $this->addSql('ALTER TABLE contact_thread_message ADD CONSTRAINT fk_74fa56f6e2904019 FOREIGN KEY (thread_id) REFERENCES contact_thread (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE workout_exercise DROP CONSTRAINT FK_76AB38AAA6CCCFC9');
        $this->addSql('ALTER TABLE workout_exercise ADD CONSTRAINT fk_76ab38aaa6cccfc9 FOREIGN KEY (workout_id) REFERENCES workout (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
