<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201019075335 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE sylius_order_target');

        $this->addSql('CREATE TABLE vendor (id SERIAL NOT NULL, restaurant_id INT DEFAULT NULL, hub_id INT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F52233F6B1E7706E ON vendor (restaurant_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F52233F66C786081 ON vendor (hub_id)');
        $this->addSql('ALTER TABLE vendor ADD CONSTRAINT FK_F52233F6B1E7706E FOREIGN KEY (restaurant_id) REFERENCES restaurant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE vendor ADD CONSTRAINT FK_F52233F66C786081 FOREIGN KEY (hub_id) REFERENCES hub (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE vendor');

        $this->addSql('CREATE TABLE sylius_order_target (id SERIAL NOT NULL, restaurant_id INT DEFAULT NULL, hub_id INT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_412637e8b1e7706e ON sylius_order_target (restaurant_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_412637e86c786081 ON sylius_order_target (hub_id)');
        $this->addSql('ALTER TABLE sylius_order_target ADD CONSTRAINT fk_412637e86c786081 FOREIGN KEY (hub_id) REFERENCES hub (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_order_target ADD CONSTRAINT fk_412637e8b1e7706e FOREIGN KEY (restaurant_id) REFERENCES restaurant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
