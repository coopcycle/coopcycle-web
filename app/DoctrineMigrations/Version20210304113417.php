<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210304113417 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE task_rrule (id SERIAL NOT NULL, store_id INT NOT NULL, rule VARCHAR(255) NOT NULL, template JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_E5082C42B092A811 ON task_rrule (store_id)');
        $this->addSql('COMMENT ON COLUMN task_rrule.rule IS \'(DC2Type:rrule)\'');
        $this->addSql('COMMENT ON COLUMN task_rrule.template IS \'(DC2Type:json_array)\'');
        $this->addSql('ALTER TABLE task_rrule ADD CONSTRAINT FK_E5082C42B092A811 FOREIGN KEY (store_id) REFERENCES store (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE task_rrule');
    }
}
