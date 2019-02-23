<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;
use Doctrine\Migrations\AbstractMigration;

final class Version20190216143748 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        $stmt = [];

        $stmt['multi_address'] = $this->connection->prepare('SELECT api_user_id, address.geo, COUNT(address.id), ARRAY_TO_STRING(ARRAY_AGG(address.id), \',\') AS address_ids FROM api_user_address JOIN address ON api_user_address.address_id = address.id GROUP BY api_user_id, address.geo HAVING COUNT(address.id) > 1');

        $stmt['multi_address']->execute();
        while ($row = $stmt['multi_address']->fetch()) {

            $addressIds = array_map('intval', explode(',', $row['address_ids']));

            // We keep the address with the smallest id
            sort($addressIds);
            $addressIdToKeep = current($addressIds);

            $addressIdsToRemove = array_diff($addressIds, [ $addressIdToKeep ]);

            $stmt['orders'] = $this->connection->executeQuery(
                'SELECT * FROM sylius_order WHERE shipping_address_id IN (:address_ids)',
                [ 'address_ids' => $addressIds ],
                [ 'address_ids' => Connection::PARAM_INT_ARRAY ]
            );

            while ($order = $stmt['orders']->fetch()) {
                $this->addSql('UPDATE sylius_order SET shipping_address_id = :address_id WHERE id = :id', [
                    'address_id' => $addressIdToKeep,
                    'id' => $order['id'],
                ]);
            }

            $stmt['tasks'] = $this->connection->executeQuery(
                'SELECT * FROM task WHERE address_id IN (:address_ids)',
                [ 'address_ids' => $addressIds ],
                [ 'address_ids' => Connection::PARAM_INT_ARRAY ]
            );

            while ($task = $stmt['tasks']->fetch()) {
                $this->addSql('UPDATE task SET address_id = :address_id WHERE id = :id', [
                    'address_id' => $addressIdToKeep,
                    'id' => $task['id'],
                ]);
            }

            foreach ($addressIdsToRemove as $addressId) {
                $this->addSql('DELETE FROM api_user_address WHERE address_id = :address_id', [
                    'address_id' => $addressId,
                ]);
                $this->addSql('DELETE FROM address WHERE id = :address_id', [
                    'address_id' => $addressId,
                ]);
            }
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
