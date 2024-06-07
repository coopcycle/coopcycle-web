<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191127205517 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE sylius_product ADD reusable_packaging_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_product ADD CONSTRAINT FK_677B9B74B26ADE57 FOREIGN KEY (reusable_packaging_id) REFERENCES reusable_packaging (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_677B9B74B26ADE57 ON sylius_product (reusable_packaging_id)');

        $stmt = [];
        $stmt['reusable_packaging'] = $this->connection->prepare('SELECT id, restaurant_id FROM reusable_packaging');
        $stmt['restaurant_product'] = $this->connection->prepare('SELECT p.id, p.reusable_packaging_enabled FROM sylius_product p JOIN restaurant_product rp ON p.id = rp.product_id WHERE p.reusable_packaging_enabled = \'t\' AND rp.restaurant_id = :restaurant_id');

        $result = $stmt['reusable_packaging']->execute();

        while ($reusablePackaging = $result->fetchAssociative()) {

            $stmt['restaurant_product']->bindParam('restaurant_id', $reusablePackaging['restaurant_id']);
            $result2 = $stmt['restaurant_product']->execute();

            while ($product = $result2->fetchAssociative()) {
                $this->addSql('UPDATE sylius_product SET reusable_packaging_id = :reusable_packaging_id WHERE id = :id', [
                    'reusable_packaging_id' => $reusablePackaging['id'],
                    'id' => $product['id'],
                ]);
            }
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP INDEX IDX_677B9B74B26ADE57');
        $this->addSql('ALTER TABLE sylius_product DROP reusable_packaging_id');
    }
}
