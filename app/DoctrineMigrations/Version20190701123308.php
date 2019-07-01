<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190701123308 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE restaurant_pledge (id SERIAL NOT NULL, address_id INT DEFAULT NULL, user_id INT NOT NULL, name VARCHAR(255) NOT NULL, state INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_43FEC5A5F5B7AF75 ON restaurant_pledge (address_id)');
        $this->addSql('CREATE INDEX IDX_43FEC5A5A76ED395 ON restaurant_pledge (user_id)');
        $this->addSql('ALTER TABLE restaurant_pledge ADD CONSTRAINT FK_43FEC5A5F5B7AF75 FOREIGN KEY (address_id) REFERENCES address (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE restaurant_pledge ADD CONSTRAINT FK_43FEC5A5A76ED395 FOREIGN KEY (user_id) REFERENCES api_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE restaurant_pledge');
    }
}
