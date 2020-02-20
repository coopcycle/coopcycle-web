<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200220211806 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE task_field_group (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE task_field_group_fields (task_field_id INT NOT NULL, task_field_group_id INT NOT NULL, PRIMARY KEY(task_field_id, task_field_group_id))');
        $this->addSql('CREATE INDEX IDX_A02215894F7EDFC ON task_field_group_fields (task_field_id)');
        $this->addSql('CREATE INDEX IDX_A022158934452825 ON task_field_group_fields (task_field_group_id)');
        $this->addSql('ALTER TABLE task_field_group_fields ADD CONSTRAINT FK_A02215894F7EDFC FOREIGN KEY (task_field_id) REFERENCES task_field_group (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE task_field_group_fields ADD CONSTRAINT FK_A022158934452825 FOREIGN KEY (task_field_group_id) REFERENCES task_field (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE task_field_group_fields DROP CONSTRAINT FK_A02215894F7EDFC');
        $this->addSql('DROP TABLE task_field_group');
        $this->addSql('DROP TABLE task_field_group_fields');
    }
}
