<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231219160417 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE edifact_message (id SERIAL NOT NULL, transporter VARCHAR(255) NOT NULL, reference VARCHAR(255) NOT NULL, direction VARCHAR(255) NOT NULL, message_type VARCHAR(255) NOT NULL, sub_message_type VARCHAR(255) DEFAULT NULL, edifact_file VARCHAR(255) DEFAULT NULL, synced_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_DC008BDAAEA34913 ON edifact_message (reference)');
        $this->addSql('CREATE TABLE tasks_edifact_messages (task_id INT NOT NULL, edifact_message_id INT NOT NULL, PRIMARY KEY(task_id, edifact_message_id))');
        $this->addSql('CREATE INDEX IDX_7C591C998DB60186 ON tasks_edifact_messages (task_id)');
        $this->addSql('CREATE INDEX IDX_7C591C993E28348A ON tasks_edifact_messages (edifact_message_id)');
        $this->addSql('ALTER TABLE tasks_edifact_messages ADD CONSTRAINT FK_7C591C998DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE tasks_edifact_messages ADD CONSTRAINT FK_7C591C993E28348A FOREIGN KEY (edifact_message_id) REFERENCES edifact_message (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tasks_edifact_messages DROP CONSTRAINT FK_7C591C993E28348A');
        $this->addSql('DROP TABLE edifact_message');
        $this->addSql('DROP TABLE tasks_edifact_messages');
    }
}
