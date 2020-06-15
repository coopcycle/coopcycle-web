<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200615075747 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE store ADD weight_required BOOLEAN DEFAULT NULL');
        $this->addSql('ALTER TABLE store ADD packages_required BOOLEAN DEFAULT NULL');

        $this->addSql('UPDATE store SET weight_required = \'false\', packages_required = \'false\'');

        $this->addSql('ALTER TABLE store ALTER weight_required SET NOT NULL');
        $this->addSql('ALTER TABLE store ALTER packages_required SET NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE store DROP weight_required');
        $this->addSql('ALTER TABLE store DROP packages_required');
    }
}
