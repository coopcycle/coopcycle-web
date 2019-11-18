<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191118131527 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE invoice_stakeholder (id SERIAL NOT NULL, invoice_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, street_address VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_9CD641992989F1FD ON invoice_stakeholder (invoice_id)');
        $this->addSql('ALTER TABLE invoice_stakeholder ADD CONSTRAINT FK_9CD641992989F1FD FOREIGN KEY (invoice_id) REFERENCES invoice (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE invoice ADD emitter_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE invoice ADD receiver_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_9065174437BC4DC6 FOREIGN KEY (emitter_id) REFERENCES invoice_stakeholder (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_90651744CD53EDB6 FOREIGN KEY (receiver_id) REFERENCES invoice_stakeholder (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_9065174437BC4DC6 ON invoice (emitter_id)');
        $this->addSql('CREATE INDEX IDX_90651744CD53EDB6 ON invoice (receiver_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE invoice DROP CONSTRAINT FK_9065174437BC4DC6');
        $this->addSql('ALTER TABLE invoice DROP CONSTRAINT FK_90651744CD53EDB6');
        $this->addSql('DROP TABLE invoice_stakeholder');
        $this->addSql('DROP INDEX IDX_9065174437BC4DC6');
        $this->addSql('DROP INDEX IDX_90651744CD53EDB6');
        $this->addSql('ALTER TABLE invoice DROP emitter_id');
        $this->addSql('ALTER TABLE invoice DROP receiver_id');
    }
}
