<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210408125848 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql('ALTER TABLE sylius_product_option ADD restaurant_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_product_option ADD CONSTRAINT FK_E4C0EBEFB1E7706E FOREIGN KEY (restaurant_id) REFERENCES restaurant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_E4C0EBEFB1E7706E ON sylius_product_option (restaurant_id)');

        $this->addSql('UPDATE sylius_product_option SET restaurant_id = rpo.restaurant_id FROM restaurant_product_option rpo WHERE rpo.option_id = id');

        $this->addSql('DROP TABLE restaurant_product_option');
    }

    public function down(Schema $schema) : void
    {
        $this->addSql('CREATE TABLE restaurant_product_option (restaurant_id INT NOT NULL, option_id INT NOT NULL, PRIMARY KEY(restaurant_id, option_id))');
        $this->addSql('CREATE INDEX idx_cb35112eb1e7706e ON restaurant_product_option (restaurant_id)');
        $this->addSql('CREATE INDEX idx_cb35112ea7c41d6f ON restaurant_product_option (option_id)');
        $this->addSql('ALTER TABLE restaurant_product_option ADD CONSTRAINT fk_cb35112ea7c41d6f FOREIGN KEY (option_id) REFERENCES sylius_product_option (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE restaurant_product_option ADD CONSTRAINT fk_cb35112eb1e7706e FOREIGN KEY (restaurant_id) REFERENCES restaurant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('INSERT INTO restaurant_product_option (restaurant_id, option_id) SELECT restaurant_id, id FROM sylius_product_option WHERE restaurant_id IS NOT NULL');

        $this->addSql('ALTER TABLE sylius_product_option DROP CONSTRAINT FK_E4C0EBEFB1E7706E');
        $this->addSql('DROP INDEX IDX_E4C0EBEFB1E7706E');
        $this->addSql('ALTER TABLE sylius_product_option DROP restaurant_id');
    }
}
