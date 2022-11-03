<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221103103812 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $taskStmt = $this->connection->prepare('SELECT id, type, weight FROM task WHERE delivery_id = :delivery_id');

        $stmt = $this->connection->prepare(
            'SELECT delivery_id FROM task WHERE delivery_id IS NOT NULL AND type = \'PICKUP\' AND weight > 0'
        );
        $stmt->execute();

        while ($t = $stmt->fetch()) {

            $taskStmt->bindParam('delivery_id', $t['delivery_id']);
            $taskStmt->execute();

            $delivery = [];
            while ($task = $taskStmt->fetch()) {
                $delivery[] = $task;
            }

            if (!$this->shouldBeMigrated($delivery)) {
                continue;
            }

            $pickups = array_filter($delivery, fn($t) => $t['type'] === 'PICKUP');
            $pickup = current($pickups);

            $this->addSql('UPDATE task SET weight = NULL WHERE id = :id', [
                'id' => $pickup['id']
            ]);

            $this->addSql('DELETE FROM task_package WHERE task_id = :id', [
                'id' => $pickup['id']
            ]);
        }
    }

    private function shouldBeMigrated(array $delivery)
    {
        $pickupWeight = 0;
        $dropoffWeightSum = 0;
        $pickupCount = 0;

        foreach ($delivery as $task) {
            if ($task['type'] === 'PICKUP') {
                $pickupCount += 1;
                $pickupWeight = $task['weight'];
            }
            if ($task['type'] === 'DROPOFF') {
                $dropoffWeightSum += $task['weight'];
            }
        }

        if ($pickupCount !== 1) {
            return false;
        }

        return $pickupWeight === $dropoffWeightSum;
    }

    public function down(Schema $schema): void
    {

    }
}
