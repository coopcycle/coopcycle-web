<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171229210727 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE schedule (date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(date))');
        $this->addSql('COMMENT ON COLUMN schedule.date IS \'(DC2Type:date_string)\'');
        $this->addSql('CREATE TABLE schedule_item (id SERIAL NOT NULL, schedule_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, courier_id INT DEFAULT NULL, delivery_id INT DEFAULT NULL, address_id INT DEFAULT NULL, position INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_FF5305454AD61721 ON schedule_item (schedule_date)');
        $this->addSql('CREATE INDEX IDX_FF530545E3D8151C ON schedule_item (courier_id)');
        $this->addSql('CREATE INDEX IDX_FF53054512136921 ON schedule_item (delivery_id)');
        $this->addSql('CREATE INDEX IDX_FF530545F5B7AF75 ON schedule_item (address_id)');
        $this->addSql('CREATE UNIQUE INDEX schedule_item_unique ON schedule_item (schedule_date, courier_id, delivery_id, address_id)');
        $this->addSql('COMMENT ON COLUMN schedule_item.schedule_date IS \'(DC2Type:date_string)\'');
        $this->addSql('ALTER TABLE schedule_item ADD CONSTRAINT FK_FF5305454AD61721 FOREIGN KEY (schedule_date) REFERENCES schedule (date) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE schedule_item ADD CONSTRAINT FK_FF530545E3D8151C FOREIGN KEY (courier_id) REFERENCES api_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE schedule_item ADD CONSTRAINT FK_FF53054512136921 FOREIGN KEY (delivery_id) REFERENCES delivery (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE schedule_item ADD CONSTRAINT FK_FF530545F5B7AF75 FOREIGN KEY (address_id) REFERENCES address (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE schedule');
        $this->addSql('DROP TABLE schedule_item');
    }
}
