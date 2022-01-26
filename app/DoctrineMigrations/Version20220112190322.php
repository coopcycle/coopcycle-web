<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220112190322 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task ADD weight INT DEFAULT NULL');

        $stmt = $this->connection->prepare('SELECT d.id AS delivery_id, d.weight, t.id AS task_id, tci.position FROM delivery d JOIN task t ON t.delivery_id = d.id AND t.type = \'DROPOFF\' JOIN task_collection_item tci ON tci.task_id = t.id WHERE d.weight IS NOT NULL');
        $stmt->execute();

        $deliveriesWithWeight = [];
        while ($deliveryWithWeight = $stmt->fetch()) {
            if (!isset($deliveriesWithWeight[$deliveryWithWeight['delivery_id']])) {
                $deliveriesWithWeight[$deliveryWithWeight['delivery_id']] = $deliveryWithWeight;
            } else {
                // In case of multiple dropoffs, we will move packages to the *FIRST* dropoff
                if ($deliveryWithWeight['position'] < $deliveriesWithWeight[$deliveryWithWeight['delivery_id']]['position']) {
                    $deliveriesWithWeight[$deliveryWithWeight['delivery_id']] = $deliveryWithWeight;
                }
            }
        }

        foreach ($deliveriesWithWeight as $deliveryWithWeight) {

            if (intval($deliveryWithWeight['weight']) === 0) {
                continue;
            }

            $this->addSql('UPDATE task SET weight = :weight WHERE id = :task_id', [
                'weight' => $deliveryWithWeight['weight'],
                'task_id' => $deliveryWithWeight['task_id']
            ]);
        }

        $this->addSql('ALTER TABLE delivery DROP weight');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE delivery ADD weight INT DEFAULT NULL');

        $stmt = $this->connection->prepare('SELECT t.delivery_id, t.id AS task_id, t.weight, tci.position FROM task t JOIN delivery d ON d.id = t.delivery_id JOIN task_collection_item tci ON tci.task_id = t.id WHERE t.weight IS NOT NULL AND t.type = \'DROPOFF\'');
        $stmt->execute();

        $weightsByDelivery = [];

        $tasksWithWeight = [];
        while ($taskWithWeight = $stmt->fetch()) {
            $tasksWithWeight[$taskWithWeight['delivery_id']] = $taskWithWeight;
        }

        foreach ($tasksWithWeight as $taskWithWeight) {
            $this->addSql('UPDATE delivery SET weight = :weight WHERE id = :delivery_id', [
                'weight' => $taskWithWeight['weight'],
                'delivery_id' => $taskWithWeight['delivery_id']
            ]);
        }


        $this->addSql('ALTER TABLE task DROP weight');
    }
}
