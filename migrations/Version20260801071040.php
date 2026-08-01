<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260801071040 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed the single RegistrationMilestoneSetting row (registration notification step, admin-editable).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE registration_milestone_setting (id UUID NOT NULL, step INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql("INSERT INTO registration_milestone_setting (id, step) VALUES ('0198f5b2-0000-7000-8000-000000000000', 500)");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE registration_milestone_setting');
    }
}
