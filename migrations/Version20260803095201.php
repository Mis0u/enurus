<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260803095201 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE workout DROP CONSTRAINT fk_649ffb72f27a94c7');
        $this->addSql('ALTER TABLE workout ADD CONSTRAINT FK_649FFB72F27A94C7 FOREIGN KEY (routine_id) REFERENCES routine (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE workout DROP CONSTRAINT FK_649FFB72F27A94C7');
        $this->addSql('ALTER TABLE workout ADD CONSTRAINT fk_649ffb72f27a94c7 FOREIGN KEY (routine_id) REFERENCES routine (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
