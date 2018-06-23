<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180623084629 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE store_product (restaurant_id INT NOT NULL, product_id INT NOT NULL, PRIMARY KEY(restaurant_id, product_id))');
        $this->addSql('CREATE INDEX IDX_CA42254AB1E7706E ON store_product (restaurant_id)');
        $this->addSql('CREATE INDEX IDX_CA42254A4584665A ON store_product (product_id)');
        $this->addSql('CREATE TABLE store_product_option (store_id INT NOT NULL, option_id INT NOT NULL, PRIMARY KEY(store_id, option_id))');
        $this->addSql('CREATE INDEX IDX_2F6C5CCAB092A811 ON store_product_option (store_id)');
        $this->addSql('CREATE INDEX IDX_2F6C5CCAA7C41D6F ON store_product_option (option_id)');
        $this->addSql('ALTER TABLE store_product ADD CONSTRAINT FK_CA42254AB1E7706E FOREIGN KEY (restaurant_id) REFERENCES store (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE store_product ADD CONSTRAINT FK_CA42254A4584665A FOREIGN KEY (product_id) REFERENCES sylius_product (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE store_product_option ADD CONSTRAINT FK_2F6C5CCAB092A811 FOREIGN KEY (store_id) REFERENCES store (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE store_product_option ADD CONSTRAINT FK_2F6C5CCAA7C41D6F FOREIGN KEY (option_id) REFERENCES sylius_product_option (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE store_product');
        $this->addSql('DROP TABLE store_product_option');
        $this->addSql('ALTER TABLE store ALTER telephone TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE store ALTER telephone DROP DEFAULT');

    }
}
