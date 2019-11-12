<?php

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170926233254 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE delivery_event (id SERIAL NOT NULL, delivery_id INT DEFAULT NULL, courier_id INT DEFAULT NULL, event_name VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_2BA5EC3512136921 ON delivery_event (delivery_id)');
        $this->addSql('CREATE INDEX IDX_2BA5EC35E3D8151C ON delivery_event (courier_id)');
        $this->addSql('ALTER TABLE delivery_event ADD CONSTRAINT FK_2BA5EC3512136921 FOREIGN KEY (delivery_id) REFERENCES delivery (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE delivery_event ADD CONSTRAINT FK_2BA5EC35E3D8151C FOREIGN KEY (courier_id) REFERENCES api_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE delivery ADD courier_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE delivery ADD CONSTRAINT FK_3781EC10E3D8151C FOREIGN KEY (courier_id) REFERENCES api_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_3781EC10E3D8151C ON delivery (courier_id)');
        $this->addSql('ALTER TABLE order_ DROP CONSTRAINT fk_d7f7910de3d8151c');
        $this->addSql('DROP INDEX idx_d7f7910de3d8151c');
        $this->addSql('ALTER TABLE order_ DROP courier_id');
        $this->addSql('ALTER TABLE order_event DROP CONSTRAINT fk_b8307e5ae3d8151c');
        $this->addSql('DROP INDEX idx_b8307e5ae3d8151c');
        $this->addSql('ALTER TABLE order_event DROP courier_id');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE delivery_event');
        $this->addSql('ALTER TABLE order_event ADD courier_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE order_event ADD CONSTRAINT fk_b8307e5ae3d8151c FOREIGN KEY (courier_id) REFERENCES api_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_b8307e5ae3d8151c ON order_event (courier_id)');
        $this->addSql('ALTER TABLE delivery DROP CONSTRAINT FK_3781EC10E3D8151C');
        $this->addSql('DROP INDEX IDX_3781EC10E3D8151C');
        $this->addSql('ALTER TABLE delivery DROP courier_id');
        $this->addSql('ALTER TABLE order_ ADD courier_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE order_ ADD CONSTRAINT fk_d7f7910de3d8151c FOREIGN KEY (courier_id) REFERENCES api_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_d7f7910de3d8151c ON order_ (courier_id)');
    }
}
