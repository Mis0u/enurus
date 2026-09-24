<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Dernière visite de chaque partie d'une connexion, pour signaler une séance créée depuis. Les
 * connexions déjà acceptées partent de « maintenant » : sans ça, tout l'historique existant
 * apparaîtrait comme nouveau au déploiement.
 */
final class Version20260924110332 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track the last visit of each party of a profile connection';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE profile_connection ADD requester_last_seen_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE profile_connection ADD addressee_last_seen_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('UPDATE profile_connection SET requester_last_seen_at = NOW(), addressee_last_seen_at = NOW() WHERE status = \'accepted\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE profile_connection DROP requester_last_seen_at');
        $this->addSql('ALTER TABLE profile_connection DROP addressee_last_seen_at');
    }
}
