<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Drop the `sessions` table: HTTP sessions now live in Valkey/Redis in production
 * (PdoSessionHandler replaced by a Redis session handler). See Version20260607090000.
 */
final class Version20260607113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop sessions table (sessions moved to Valkey/Redis)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS sessions');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sessions (sess_id VARCHAR(128) NOT NULL PRIMARY KEY, sess_data BYTEA NOT NULL, sess_lifetime INTEGER NOT NULL, sess_time INTEGER NOT NULL)');
        $this->addSql('CREATE INDEX sessions_sess_lifetime_idx ON sessions (sess_lifetime)');
    }
}
