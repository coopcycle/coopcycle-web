<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20181107222601 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs

        $stmt['orders'] = $this->connection->prepare('SELECT * FROM sylius_order WHERE restaurant_id IS NULL AND shipped_at IS NULL');
        $stmt['delivery'] = $this->connection->prepare('SELECT * FROM delivery WHERE order_id = :order_id');
        $stmt['tasks'] = $this->connection->prepare('SELECT * FROM task_collection_item JOIN task ON task_collection_item.task_id = task.id WHERE task_collection_item.parent_id = :delivery_id');

        $result = $stmt['orders']->execute();
        while ($order = $result->fetchAssociative()) {

            $stmt['delivery']->bindParam('order_id', $order['id']);
            $result2 = $stmt['delivery']->execute();

            $delivery = $result2->fetchAssociative();

            $stmt['tasks']->bindParam('delivery_id', $delivery['id']);
            $result3 = $stmt['tasks']->execute();

            while ($task = $result3->fetchAssociative()) {
                if ($task['type'] === 'DROPOFF') {
                    $this->addSql('UPDATE sylius_order SET shipped_at = :shipped_at WHERE id = :order_id', [
                        'shipped_at' => $task['done_before'],
                        'order_id' => $order['id'],
                    ]);
                }
            }
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
