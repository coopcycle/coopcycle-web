<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180409151356 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        // update the pricing rules to use cents instead of floating point amounts
        $stmt = $this->connection->prepare('SELECT id, price FROM pricing_rule');
        $stmt->execute();
        while ($row = $stmt->fetch()) {

            if (is_numeric($row['price'])) {
                $row['price'] = (string)((float)$row['price'] * 100);
            } else {
                // we can safely use these regexp as distances and weight are whole numbers
                // prices entered with 1 decimal precision
                $row['price'] = preg_replace('/([\d+])\.([\d]{1})/', '$1$2', $row['price']);
                // prices entered with more than 1 decimal precision
                $row['price'] = preg_replace('/([\d+])\.([\d]{2})[\d]+/', '$1$2', $row['price']);
            }
            $this->addSql('UPDATE pricing_rule SET price = :price WHERE id = :id', $row);
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
