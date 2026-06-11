<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Replace Google SSO with the self-hosted Authelia OIDC SSO:
 * drop user.google_id, add user.authelia_id (unique). Google was never enabled in
 * prod (empty client id) so no data is lost.
 */
final class Version20260611120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace google_id with authelia_id on user (Google SSO → Authelia OIDC)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD authelia_id VARCHAR(100) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D6496CFCAA04 ON "user" (authelia_id)');
        // Dropping the column also drops UNIQ_8D93D64976F5C865 (Postgres).
        $this->addSql('ALTER TABLE "user" DROP google_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD google_id VARCHAR(100) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D64976F5C865 ON "user" (google_id)');
        $this->addSql('ALTER TABLE "user" DROP authelia_id');
    }
}
