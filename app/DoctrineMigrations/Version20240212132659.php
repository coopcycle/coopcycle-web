<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240212132659 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task ADD has_incidents BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('UPDATE task SET has_incidents = true WHERE failure_reason IS NOT NULL');
        $this->addSql('ALTER TABLE task DROP failure_reason');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task ADD failure_reason VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE task DROP has_incidents');
    }
}
