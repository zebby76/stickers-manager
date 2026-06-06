<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260604065138 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "user" ADD approved BOOLEAN NOT NULL');
        $this->addSql('ALTER TABLE "user" ADD active BOOLEAN NOT NULL');
        $this->addSql('ALTER TABLE "user" ADD google_id VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ALTER password DROP NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D64976F5C865 ON "user" (google_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_8D93D64976F5C865');
        $this->addSql('ALTER TABLE "user" DROP approved');
        $this->addSql('ALTER TABLE "user" DROP active');
        $this->addSql('ALTER TABLE "user" DROP google_id');
        $this->addSql('ALTER TABLE "user" ALTER password SET NOT NULL');
    }
}
