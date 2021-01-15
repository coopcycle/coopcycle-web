<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201014205301 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE restaurant_fulfillment_methods (restaurant_id INT NOT NULL, method_id INT NOT NULL, PRIMARY KEY(restaurant_id, method_id))');
        $this->addSql('CREATE INDEX IDX_BCA3AD83B1E7706E ON restaurant_fulfillment_methods (restaurant_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BCA3AD8319883967 ON restaurant_fulfillment_methods (method_id)');
        $this->addSql('ALTER TABLE restaurant_fulfillment_methods ADD CONSTRAINT FK_BCA3AD83B1E7706E FOREIGN KEY (restaurant_id) REFERENCES restaurant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE restaurant_fulfillment_methods ADD CONSTRAINT FK_BCA3AD8319883967 FOREIGN KEY (method_id) REFERENCES restaurant_fulfillment_method (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('INSERT INTO restaurant_fulfillment_methods (restaurant_id, method_id) SELECT restaurant_id, id FROM restaurant_fulfillment_method');

        $this->addSql('ALTER TABLE restaurant_fulfillment_method DROP CONSTRAINT fk_ca44e9e4b1e7706e');
        $this->addSql('DROP INDEX idx_ca44e9e4b1e7706e');
        $this->addSql('DROP INDEX uniq_ca44e9e4b1e7706e8cde5729');
        $this->addSql('ALTER TABLE restaurant_fulfillment_method DROP restaurant_id');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE restaurant_fulfillment_method ADD restaurant_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE restaurant_fulfillment_method ADD CONSTRAINT fk_ca44e9e4b1e7706e FOREIGN KEY (restaurant_id) REFERENCES restaurant (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_ca44e9e4b1e7706e ON restaurant_fulfillment_method (restaurant_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_ca44e9e4b1e7706e8cde5729 ON restaurant_fulfillment_method (restaurant_id, type)');

        $this->addSql('UPDATE restaurant_fulfillment_method rfm SET restaurant_id = rfms.restaurant_id FROM restaurant_fulfillment_methods rfms WHERE rfms.method_id = rfm.id');

        $this->addSql('DROP TABLE restaurant_fulfillment_methods');
    }
}
