<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Objectifs de régularité (« X séances par jour/semaine pendant N semaines »), créés depuis
 * l'onglet Calendrier de Mes séances.
 */
final class Version20260928120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the regularity goal table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE regularity_goal (id UUID NOT NULL, sessions_per_period SMALLINT NOT NULL, period VARCHAR(10) NOT NULL, duration SMALLINT NOT NULL, start_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, owner_id UUID NOT NULL, created_by_id UUID DEFAULT NULL, updated_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_98D55C177E3C61F9 ON regularity_goal (owner_id)');
        $this->addSql('CREATE INDEX IDX_98D55C17B03A8386 ON regularity_goal (created_by_id)');
        $this->addSql('CREATE INDEX IDX_98D55C17896DBBDE ON regularity_goal (updated_by_id)');
        $this->addSql('ALTER TABLE regularity_goal ADD CONSTRAINT FK_98D55C177E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE regularity_goal ADD CONSTRAINT FK_98D55C17B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE regularity_goal ADD CONSTRAINT FK_98D55C17896DBBDE FOREIGN KEY (updated_by_id) REFERENCES users (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE regularity_goal DROP CONSTRAINT FK_98D55C177E3C61F9');
        $this->addSql('ALTER TABLE regularity_goal DROP CONSTRAINT FK_98D55C17B03A8386');
        $this->addSql('ALTER TABLE regularity_goal DROP CONSTRAINT FK_98D55C17896DBBDE');
        $this->addSql('DROP TABLE regularity_goal');
    }
}
