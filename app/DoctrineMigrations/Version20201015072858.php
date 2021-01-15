<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201015072858 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE hub_fulfillment_method (hub_id INT NOT NULL, method_id INT NOT NULL, PRIMARY KEY(hub_id, method_id))');
        $this->addSql('CREATE INDEX IDX_38E964826C786081 ON hub_fulfillment_method (hub_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_38E9648219883967 ON hub_fulfillment_method (method_id)');
        $this->addSql('ALTER TABLE hub_fulfillment_method ADD CONSTRAINT FK_38E964826C786081 FOREIGN KEY (hub_id) REFERENCES hub (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE hub_fulfillment_method ADD CONSTRAINT FK_38E9648219883967 FOREIGN KEY (method_id) REFERENCES restaurant_fulfillment_method (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE hub_fulfillment_method');
    }
}
