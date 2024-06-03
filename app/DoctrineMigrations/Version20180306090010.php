<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180306090010 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs

        $stmt = $this->connection->prepare('SELECT MAX(id) FROM delivery');
        $result = $stmt->execute();
        $lastDeliveryId = $result->fetchOne();

        $stmt = $this->connection->prepare('SELECT MAX(id) FROM task_list');
        $result = $stmt->execute();
        $lastTaskListId = $result->fetchOne();

        $taskCollectionId = (max($lastDeliveryId, $lastTaskListId) + 1);

        $this->addSql("ALTER SEQUENCE task_collection_id_seq RESTART WITH {$taskCollectionId}");
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
