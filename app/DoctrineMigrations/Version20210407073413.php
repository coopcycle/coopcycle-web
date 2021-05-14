<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210407073413 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql('ALTER TABLE sylius_product ADD restaurant_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_product ADD CONSTRAINT FK_677B9B74B1E7706E FOREIGN KEY (restaurant_id) REFERENCES restaurant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_677B9B74B1E7706E ON sylius_product (restaurant_id)');

        $this->addSql('UPDATE sylius_product SET restaurant_id = rp.restaurant_id FROM restaurant_product rp WHERE rp.product_id = id');

        $this->addSql('DROP TABLE restaurant_product');
    }

    public function down(Schema $schema) : void
    {
        $this->addSql('CREATE TABLE restaurant_product (product_id INT NOT NULL, restaurant_id INT NOT NULL, PRIMARY KEY(restaurant_id, product_id))');
        $this->addSql('CREATE INDEX idx_190158d8b1e7706e ON restaurant_product (restaurant_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_190158d84584665a ON restaurant_product (product_id)');
        $this->addSql('ALTER TABLE restaurant_product ADD CONSTRAINT fk_190158d8b1e7706e FOREIGN KEY (restaurant_id) REFERENCES restaurant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE restaurant_product ADD CONSTRAINT fk_190158d84584665a FOREIGN KEY (product_id) REFERENCES sylius_product (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('INSERT INTO restaurant_product (restaurant_id, product_id) SELECT restaurant_id, id FROM sylius_product WHERE restaurant_id IS NOT NULL');

        $this->addSql('ALTER TABLE sylius_product DROP CONSTRAINT FK_677B9B74B1E7706E');
        $this->addSql('DROP INDEX IDX_677B9B74B1E7706E');
        $this->addSql('ALTER TABLE sylius_product DROP restaurant_id');
    }
}
