<?php declare(strict_types = 1);

namespace Application\Migrations;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\ScheduleItem;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171230131300 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE schedule_item ADD status VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE schedule_item ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE schedule_item ADD updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');

        $stmts = [
            'select_schedule_items' => $this->connection->prepare('SELECT * FROM schedule_item'),
            'select_delivery' => $this->connection->prepare('SELECT * FROM delivery WHERE id = :id'),
        ];

        $stmts['select_schedule_items']->execute();
        while ($scheduleItem = $stmts['select_schedule_items']->fetch()) {

            $stmts['select_delivery']->bindParam('id', $scheduleItem['delivery_id']);
            $stmts['select_delivery']->execute();

            $delivery = $stmts['select_delivery']->fetch();

            $isPickup = $delivery['origin_address_id'] === $scheduleItem['address_id'];

            $status = ScheduleItem::STATUS_TODO;
            if ($isPickup && $delivery['status'] === Delivery::STATUS_PICKED) {
                $status = ScheduleItem::STATUS_DONE;
            }
            if (!$isPickup && $delivery['status'] === Delivery::STATUS_DELIVERED) {
                $status = ScheduleItem::STATUS_DONE;
            }

            $this->addSql('UPDATE schedule_item SET status = :status WHERE id = :id', [
                'status' => $status,
                'id' => $scheduleItem['id']
            ]);
        }

        $this->addSql('UPDATE schedule_item SET created_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP');

        $this->addSql('ALTER TABLE schedule_item ALTER COLUMN status SET NOT NULL');
        $this->addSql('ALTER TABLE schedule_item ALTER COLUMN created_at SET NOT NULL');
        $this->addSql('ALTER TABLE schedule_item ALTER COLUMN updated_at SET NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE schedule_item DROP status');
        $this->addSql('ALTER TABLE schedule_item DROP created_at');
        $this->addSql('ALTER TABLE schedule_item DROP updated_at');
    }
}
