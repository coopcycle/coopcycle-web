<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201027120051 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE sylius_product_image (id SERIAL NOT NULL, product_id INT NOT NULL, image_name VARCHAR(255) DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_88C64B2D4584665A ON sylius_product_image (product_id)');
        $this->addSql('ALTER TABLE sylius_product_image ADD CONSTRAINT FK_88C64B2D4584665A FOREIGN KEY (product_id) REFERENCES sylius_product (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('INSERT INTO sylius_product_image (product_id, image_name, updated_at) SELECT id, image_name, updated_at FROM sylius_product WHERE image_name IS NOT NULL');

        $this->addSql('ALTER TABLE sylius_product DROP image_name');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE sylius_product ADD image_name VARCHAR(255) DEFAULT NULL');

        $this->addSql('UPDATE sylius_product p SET image_name = i.image_name FROM sylius_product_image i WHERE i.product_id = p.id');

        $this->addSql('DROP TABLE sylius_product_image');
    }
}
