<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20181005192815 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        $stmt = $this->connection->prepare('SELECT id, number FROM sylius_order WHERE number IS NOT NULL');

        $stmt->execute();
        while ($order = $stmt->fetch()) {
            $this->addSql('UPDATE sylius_order SET number = :number WHERE id = :id', [
                'number' => strtoupper(base_convert($order['id'], 10, 36)),
                'id' => $order['id'],
            ]);
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
