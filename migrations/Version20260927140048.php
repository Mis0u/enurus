<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Email à chaque demande de connexion reçue, actif par défaut — y compris pour les comptes
 * existants, qui peuvent le couper depuis la page Connexions.
 */
final class Version20260927140048 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the connection request email preference to users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD email_on_connection_request BOOLEAN DEFAULT true NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP email_on_connection_request');
    }
}
