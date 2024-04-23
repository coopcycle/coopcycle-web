<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240423150752 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE incident (id SERIAL NOT NULL, task_id INT DEFAULT NULL, created_by_id INT DEFAULT NULL, title VARCHAR(255) NOT NULL, status VARCHAR(32) NOT NULL, priority INT NOT NULL, failure_reason_code VARCHAR(32) DEFAULT NULL, description VARCHAR(65535) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_3D03A11A8DB60186 ON incident (task_id)');
        $this->addSql('CREATE INDEX IDX_3D03A11AB03A8386 ON incident (created_by_id)');
        $this->addSql('CREATE TABLE incident_event (id SERIAL NOT NULL, incident_id INT DEFAULT NULL, created_by_id INT DEFAULT NULL, type VARCHAR(255) NOT NULL, message VARCHAR(4096) DEFAULT NULL, metadata JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_609AA8CD59E53FB9 ON incident_event (incident_id)');
        $this->addSql('CREATE INDEX IDX_609AA8CDB03A8386 ON incident_event (created_by_id)');
        $this->addSql('COMMENT ON COLUMN incident_event.metadata IS \'(DC2Type:json_array)\'');
        $this->addSql('CREATE TABLE incident_image (id SERIAL NOT NULL, incident_id INT DEFAULT NULL, image_name VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_9E09A63559E53FB9 ON incident_image (incident_id)');
        $this->addSql('ALTER TABLE incident ADD CONSTRAINT FK_3D03A11A8DB60186 FOREIGN KEY (task_id) REFERENCES task (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE incident ADD CONSTRAINT FK_3D03A11AB03A8386 FOREIGN KEY (created_by_id) REFERENCES api_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE incident_event ADD CONSTRAINT FK_609AA8CD59E53FB9 FOREIGN KEY (incident_id) REFERENCES incident (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE incident_event ADD CONSTRAINT FK_609AA8CDB03A8386 FOREIGN KEY (created_by_id) REFERENCES api_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE incident_image ADD CONSTRAINT FK_9E09A63559E53FB9 FOREIGN KEY (incident_id) REFERENCES incident (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE edifact_message ADD metadata JSON DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN edifact_message.metadata IS \'(DC2Type:json_array)\'');
        $this->addSql('ALTER TABLE restaurant ADD failure_reason_set_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE restaurant ADD CONSTRAINT FK_EB95123FCC1689AF FOREIGN KEY (failure_reason_set_id) REFERENCES failure_reason_set (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_EB95123FCC1689AF ON restaurant (failure_reason_set_id)');
        $this->addSql('ALTER TABLE task DROP has_incidents');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE incident_event DROP CONSTRAINT FK_609AA8CD59E53FB9');
        $this->addSql('ALTER TABLE incident_image DROP CONSTRAINT FK_9E09A63559E53FB9');
        $this->addSql('DROP TABLE incident');
        $this->addSql('DROP TABLE incident_event');
        $this->addSql('DROP TABLE incident_image');
        $this->addSql('ALTER TABLE edifact_message DROP metadata');
        $this->addSql('ALTER TABLE task ADD has_incidents BOOLEAN DEFAULT \'false\' NOT NULL');
        $this->addSql('ALTER TABLE restaurant DROP CONSTRAINT FK_EB95123FCC1689AF');
        $this->addSql('DROP INDEX IDX_EB95123FCC1689AF');
        $this->addSql('ALTER TABLE restaurant DROP failure_reason_set_id');
    }
}
