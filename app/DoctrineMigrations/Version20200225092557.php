<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200225092557 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql("UPDATE address SET contact_name = TRIM(CONCAT(first_name, ' ', last_name)) WHERE COALESCE(contact_name, '') != '' AND (COALESCE(first_name, '') != '' OR COALESCE(last_name, '') != '')");
        $this->addSql('ALTER TABLE address DROP first_name');
        $this->addSql('ALTER TABLE address DROP last_name');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE address ADD first_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE address ADD last_name VARCHAR(255) DEFAULT NULL');
    }
}
