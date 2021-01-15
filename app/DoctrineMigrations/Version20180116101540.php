<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180116101540 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE task (id SERIAL NOT NULL, delivery_id INT DEFAULT NULL, address_id INT DEFAULT NULL, type VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, done_after TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, done_before TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, comments TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_527EDB2512136921 ON task (delivery_id)');
        $this->addSql('CREATE INDEX IDX_527EDB25F5B7AF75 ON task (address_id)');

        $this->addSql('CREATE TABLE task_assignment (task_id INT NOT NULL, courier_id INT NOT NULL, position INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(task_id, courier_id))');
        $this->addSql('CREATE INDEX IDX_2CD60F158DB60186 ON task_assignment (task_id)');
        $this->addSql('CREATE INDEX IDX_2CD60F15E3D8151C ON task_assignment (courier_id)');

        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB2512136921 FOREIGN KEY (delivery_id) REFERENCES delivery (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB25F5B7AF75 FOREIGN KEY (address_id) REFERENCES address (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE task_assignment ADD CONSTRAINT FK_2CD60F158DB60186 FOREIGN KEY (task_id) REFERENCES task (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE task_assignment ADD CONSTRAINT FK_2CD60F15E3D8151C FOREIGN KEY (courier_id) REFERENCES api_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE task_list (date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, courier_id INT NOT NULL, duration DOUBLE PRECISION NOT NULL, distance DOUBLE PRECISION NOT NULL, polyline TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(date, courier_id))');

        $this->addSql('CREATE INDEX IDX_377B6C63E3D8151C ON task_list (courier_id)');
        $this->addSql('COMMENT ON COLUMN task_list.date IS \'(DC2Type:date_string)\'');
        $this->addSql('ALTER TABLE task_list ADD CONSTRAINT FK_377B6C63E3D8151C FOREIGN KEY (courier_id) REFERENCES api_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        // Create tasks from deliveries
        $stmt = $this->connection->prepare('SELECT * FROM delivery');
        $stmt->execute();
        while ($delivery = $stmt->fetch()) {

            $dropoffDoneBefore = new \DateTime($delivery['date']);

            $dropoffDoneAfter = clone $dropoffDoneBefore;
            $dropoffDoneAfter->modify('-15 minutes');

            $pickupDoneBefore = clone $dropoffDoneAfter;
            $pickupDoneBefore->modify('-15 minutes');

            $pickupDoneAfter = clone $pickupDoneBefore;
            $pickupDoneAfter->modify('-15 minutes');

            $this->addSql('INSERT INTO task (delivery_id, address_id, type, status, done_after, done_before, created_at, updated_at) VALUES (:delivery_id, :address_id, \'PICKUP\', \'TODO\', :done_after, :done_before, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)', [
                'delivery_id' => $delivery['id'],
                'address_id' => $delivery['origin_address_id'],
                'done_after' => $pickupDoneAfter->format('Y-m-d H:i:s'),
                'done_before' => $pickupDoneBefore->format('Y-m-d H:i:s')
            ]);

            $this->addSql('INSERT INTO task (delivery_id, address_id, type, status, done_after, done_before, created_at, updated_at) VALUES (:delivery_id, :address_id, \'DROPOFF\', \'TODO\', :done_after, :done_before, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)', [
                'delivery_id' => $delivery['id'],
                'address_id' => $delivery['delivery_address_id'],
                'done_after' => $dropoffDoneAfter->format('Y-m-d H:i:s'),
                'done_before' => $dropoffDoneBefore->format('Y-m-d H:i:s')
            ]);
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE task_list');
        $this->addSql('DROP TABLE task_assignment');
        $this->addSql('DROP TABLE task');
    }
}
