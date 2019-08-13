<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190812081948 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE api_log (id SERIAL NOT NULL, method VARCHAR(6) NOT NULL, request_uri TEXT NOT NULL, status_code INT NOT NULL, request_headers TEXT DEFAULT NULL, request_body TEXT DEFAULT NULL, response_headers TEXT DEFAULT NULL, response_body TEXT DEFAULT NULL, authenticated BOOLEAN DEFAULT \'f\' NOT NULL, username VARCHAR(255) DEFAULT NULL, roles JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN api_log.roles IS \'(DC2Type:json_array)\'');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE api_log');
    }
}
