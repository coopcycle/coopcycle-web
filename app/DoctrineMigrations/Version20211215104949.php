<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211215104949 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE task_package (id SERIAL NOT NULL, task_id INT NOT NULL, package_id INT NOT NULL, quantity INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_C1894D7F8DB60186 ON task_package (task_id)');
        $this->addSql('CREATE INDEX IDX_C1894D7FF44CABFF ON task_package (package_id)');
        $this->addSql('ALTER TABLE task_package ADD CONSTRAINT FK_C1894D7F8DB60186 FOREIGN KEY (task_id) REFERENCES task (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE task_package ADD CONSTRAINT FK_C1894D7FF44CABFF FOREIGN KEY (package_id) REFERENCES package (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $stmt = $this->connection->prepare('SELECT t.delivery_id, t.id AS task_id, tci.position, dp.package_id, dp.quantity FROM delivery_package dp JOIN task_collection_item tci ON dp.delivery_id = tci.parent_id JOIN task t ON tci.task_id = t.id WHERE t.type = \'DROPOFF\'');
        $stmt->execute();

        $deliveryPackages = [];
        while ($deliveryPackage = $stmt->fetch()) {
            if (!isset($deliveryPackages[$deliveryPackage['delivery_id']])) {
                $deliveryPackages[$deliveryPackage['delivery_id']] = $deliveryPackage;
            } else {
                // In case of multiple dropoffs, we will move packages to the *FIRST* dropoff
                if ($deliveryPackage['position'] < $deliveryPackages[$deliveryPackage['delivery_id']]['position']) {
                    $deliveryPackages[$deliveryPackage['delivery_id']] = $deliveryPackage;
                }
            }
        }

        foreach ($deliveryPackages as $deliveryPackage) {
            $this->addSql('INSERT INTO task_package (task_id, package_id, quantity) VALUES (:task_id, :package_id, :quantity)', [
                'task_id' => $deliveryPackage['task_id'],
                'package_id' => $deliveryPackage['package_id'],
                'quantity' => $deliveryPackage['quantity'],
            ]);
        }

        $this->addSql('DROP TABLE delivery_package');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE delivery_package (id SERIAL NOT NULL, delivery_id INT NOT NULL, package_id INT NOT NULL, quantity INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_d476d84bf44cabff ON delivery_package (package_id)');
        $this->addSql('CREATE INDEX idx_d476d84b12136921 ON delivery_package (delivery_id)');
        $this->addSql('ALTER TABLE delivery_package ADD CONSTRAINT fk_d476d84bf44cabff FOREIGN KEY (package_id) REFERENCES package (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE delivery_package ADD CONSTRAINT fk_d476d84b12136921 FOREIGN KEY (delivery_id) REFERENCES delivery (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $stmt = $this->connection->prepare('SELECT t.delivery_id, t.id AS task_id, tci.position, tp.package_id, tp.quantity FROM task_package tp JOIN task_collection_item tci ON tp.task_id = tci.task_id JOIN task t ON tci.task_id = t.id');
        $stmt->execute();

        $packagesByDelivery = [];

        while ($taskPackage = $stmt->fetch()) {
            $packagesByDelivery[$taskPackage['delivery_id']][] = $taskPackage;
        }

        $packagesByDeliveryComputed = [];
        foreach ($packagesByDelivery as $deliveryId => $item) {
            $packagesByDeliveryComputed[$deliveryId] = array_reduce($item, function ($carry, $item) {
                if (isset($carry[$item['package_id']])) {
                    $carry[$item['package_id']] += $item['quantity'];
                } else {
                    $carry[$item['package_id']] = $item['quantity'];
                }

                return $carry;
            }, []);
        }

        foreach ($packagesByDeliveryComputed as $deliveryId => $packages) {
            foreach ($packages as $packageId => $quantity) {
                $this->addSql('INSERT INTO delivery_package (delivery_id, package_id, quantity) VALUES (:delivery_id, :package_id, :quantity)', [
                    'delivery_id' => $deliveryId,
                    'package_id' => $packageId,
                    'quantity' => $quantity,
                ]);
            }
        }

        $this->addSql('DROP TABLE task_package');
    }
}
