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
        $pickupSql =
            'select id, delivery_id, weight from task where type = \'PICKUP\' and delivery_id is not null and weight > 0';
        $dropoffSql =
            'select delivery_id, sum(weight) as total_weight from task where type = \'DROPOFF\' and delivery_id is not null and weight > 0 group by delivery_id';

        $stmt = $this->connection->prepare(
            sprintf('select p.delivery_id, p.id as pickup_id, p.weight as pickup_weight, d.total_weight as dropoffs_weight from (%s) p join (%s) d on p.delivery_id = d.delivery_id where p.weight = d.total_weight', $pickupSql, $dropoffSql)
        );
        $result = $stmt->execute();

        while ($row = $result->fetchAssociative()) {

            $this->addSql('UPDATE task SET weight = NULL WHERE id = :id', [
                'id' => $row['pickup_id']
            ]);

            $this->addSql('DELETE FROM task_package WHERE task_id = :id', [
                'id' => $row['pickup_id']
            ]);
        }
    }

    public function down(Schema $schema): void
    {

    }
}
