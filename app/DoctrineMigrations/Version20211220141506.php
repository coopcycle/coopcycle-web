<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211220141506 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $stmt = $this->connection->prepare('SELECT id, delivery_id, comments FROM task WHERE type = \'PICKUP\' AND delivery_id IN (SELECT delivery_id FROM urbantz_delivery)');
        $result = $stmt->execute();

        while ($task = $result->fetchAssociative()) {

            if (1 === preg_match('/([0-9\.]+) kg/im', $task['comments'], $matches)) {

                [ $kg, $g ] = explode('.', $matches[1]);

                $weight = (intval($kg) * 1000) + intval($g);

                $this->addSql('UPDATE delivery SET weight = :weight WHERE id = :delivery_id', [
                    'weight' => $weight,
                    'delivery_id' => $task['delivery_id'],
                ]);
            }
        }
    }

    public function down(Schema $schema): void
    {
    }
}
