<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210406074131 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE restaurant ADD hub_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE restaurant ADD CONSTRAINT FK_EB95123F6C786081 FOREIGN KEY (hub_id) REFERENCES hub (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_EB95123F6C786081 ON restaurant (hub_id)');

        $this->addSql('UPDATE restaurant SET hub_id = hr.hub_id FROM hub_restaurant hr WHERE hr.restaurant_id = restaurant.id');

        $this->addSql('DROP VIEW IF EXISTS view_restaurant_order');
        $this->addSql('DROP TABLE hub_restaurant');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE hub_restaurant (hub_id INT NOT NULL, restaurant_id INT NOT NULL, PRIMARY KEY(hub_id, restaurant_id))');
        $this->addSql('CREATE INDEX idx_eed48673b1e7706e ON hub_restaurant (restaurant_id)');
        $this->addSql('CREATE INDEX idx_eed486736c786081 ON hub_restaurant (hub_id)');
        $this->addSql('ALTER TABLE hub_restaurant ADD CONSTRAINT fk_d2ec3b0bb1e7706e FOREIGN KEY (restaurant_id) REFERENCES restaurant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE hub_restaurant ADD CONSTRAINT fk_d2ec3b0b6c786081 FOREIGN KEY (hub_id) REFERENCES hub (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('INSERT INTO hub_restaurant (hub_id, restaurant_id) SELECT hub_id, id FROM restaurant WHERE hub_id IS NOT NULL');

        $this->addSql('ALTER TABLE restaurant DROP CONSTRAINT FK_EB95123F6C786081');
        $this->addSql('DROP INDEX IDX_EB95123F6C786081');
        $this->addSql('ALTER TABLE restaurant DROP hub_id');
    }
}
