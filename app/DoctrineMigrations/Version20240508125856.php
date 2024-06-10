<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240508125856 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create task_list_item table and add columns for task_list table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE task_list_item (id SERIAL NOT NULL, task_id INT DEFAULT NULL, tour_id INT DEFAULT NULL, parent_id INT DEFAULT NULL, position INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_25FF37A68DB60186 ON task_list_item (task_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_25FF37A615ED8D43 ON task_list_item (tour_id)');
        $this->addSql('CREATE INDEX IDX_25FF37A6727ACA70 ON task_list_item (parent_id)');
        $this->addSql('ALTER TABLE task_list_item ADD CONSTRAINT FK_25FF37A68DB60186 FOREIGN KEY (task_id) REFERENCES task (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE task_list_item ADD CONSTRAINT FK_25FF37A615ED8D43 FOREIGN KEY (tour_id) REFERENCES tour (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE task_list_item ADD CONSTRAINT FK_25FF37A6727ACA70 FOREIGN KEY (parent_id) REFERENCES task_list (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE task_list ADD distance INT');
        $this->addSql('ALTER TABLE task_list ADD duration INT');
        $this->addSql('ALTER TABLE task_list ADD polyline TEXT');
        $this->addSql('ALTER TABLE task_list ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE task_list ADD updated_at TIMESTAMP(0) WITHOUT TIME ZONE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE task_list_item');
        $this->addSql('ALTER TABLE task_list DROP distance');
        $this->addSql('ALTER TABLE task_list DROP duration');
        $this->addSql('ALTER TABLE task_list DROP polyline');
        $this->addSql('ALTER TABLE task_list DROP created_at');
        $this->addSql('ALTER TABLE task_list DROP updated_at');
    }
}
