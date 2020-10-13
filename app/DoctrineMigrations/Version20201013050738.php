<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201013050738 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE hub (id SERIAL NOT NULL, address_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_4871CE4DF5B7AF75 ON hub (address_id)');
        $this->addSql('CREATE TABLE hub_restaurant (hub_id INT NOT NULL, restaurant_id INT NOT NULL, PRIMARY KEY(hub_id, restaurant_id))');
        $this->addSql('CREATE INDEX IDX_D2EC3B0B6C786081 ON hub_restaurant (hub_id)');
        $this->addSql('CREATE INDEX IDX_D2EC3B0BB1E7706E ON hub_restaurant (restaurant_id)');
        $this->addSql('ALTER TABLE hub ADD CONSTRAINT FK_4871CE4DF5B7AF75 FOREIGN KEY (address_id) REFERENCES address (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE hub_restaurant ADD CONSTRAINT FK_D2EC3B0B6C786081 FOREIGN KEY (hub_id) REFERENCES hub (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE hub_restaurant ADD CONSTRAINT FK_D2EC3B0BB1E7706E FOREIGN KEY (restaurant_id) REFERENCES restaurant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE hub_restaurant DROP CONSTRAINT FK_D2EC3B0B6C786081');
        $this->addSql('DROP TABLE hub');
        $this->addSql('DROP TABLE hub_restaurant');
    }
}
