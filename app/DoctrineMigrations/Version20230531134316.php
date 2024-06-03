<?php

declare(strict_types=1);

namespace Application\Migrations;

use AppBundle\Entity\ReusablePackaging;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230531134316 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reusable_packaging ADD type VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE reusable_packaging ADD data JSON DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN reusable_packaging.data IS \'(DC2Type:json_array)\'');

        $stmt = $this->connection->prepare('SELECT * FROM reusable_packaging');
        $result = $stmt->execute();

        while ($row = $result->fetchAssociative()) {

            $name = strtolower($row['name']);

            $type = ReusablePackaging::TYPE_INTERNAL;
            if ($name === 'loopeat') {
                $type = ReusablePackaging::TYPE_LOOPEAT;
            }
            if ($name === 'dabba') {
                $type = ReusablePackaging::TYPE_DABBA;
            }
            if ($name === 'vytal') {
                $type = ReusablePackaging::TYPE_VYTAL;
            }

            $this->addSql('UPDATE reusable_packaging SET type = :type, data = \'{}\' WHERE id = :id', [
                'type' => $type,
                'id' => $row['id'],
            ]);
        }

        $this->addSql('ALTER TABLE reusable_packaging ALTER type SET NOT NULL');
        $this->addSql('ALTER TABLE reusable_packaging ALTER data SET NOT NULL');

        $this->addSql('CREATE TABLE reusable_packagings (id SERIAL NOT NULL, product_id INT DEFAULT NULL, reusable_packaging_id INT DEFAULT NULL, units DOUBLE PRECISION NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_8F963D044584665A ON reusable_packagings (product_id)');
        $this->addSql('CREATE INDEX IDX_8F963D04B26ADE57 ON reusable_packagings (reusable_packaging_id)');
        $this->addSql('ALTER TABLE reusable_packagings ADD CONSTRAINT FK_8F963D044584665A FOREIGN KEY (product_id) REFERENCES sylius_product (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE reusable_packagings ADD CONSTRAINT FK_8F963D04B26ADE57 FOREIGN KEY (reusable_packaging_id) REFERENCES reusable_packaging (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $stmt = $this->connection->prepare('SELECT id AS product_id, reusable_packaging_id, reusable_packaging_unit AS units FROM sylius_product WHERE reusable_packaging_id IS NOT NULL');
        $result = $stmt->execute();

        while ($row = $result->fetchAssociative()) {
            $this->addSql('INSERT INTO reusable_packagings (product_id, reusable_packaging_id, units) VALUES (:product_id, :reusable_packaging_id, :units)', $row);
        }

        $this->addSql('ALTER TABLE sylius_product DROP CONSTRAINT fk_677b9b74b26ade57');
        $this->addSql('DROP INDEX idx_677b9b74b26ade57');
        $this->addSql('ALTER TABLE sylius_product DROP reusable_packaging_id');
        $this->addSql('ALTER TABLE sylius_product DROP reusable_packaging_unit');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
