<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231003104221 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE failure_reason (code VARCHAR(32) NOT NULL, rule_set_id INT NOT NULL, description VARCHAR(255) NOT NULL, metadata JSON NOT NULL, PRIMARY KEY(code))');
        $this->addSql('CREATE INDEX IDX_902D57448B51FD88 ON failure_reason (rule_set_id)');
        $this->addSql('COMMENT ON COLUMN failure_reason.metadata IS \'(DC2Type:json_array)\'');
        $this->addSql('CREATE TABLE failure_reason_set (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE failure_reason ADD CONSTRAINT FK_902D57448B51FD88 FOREIGN KEY (rule_set_id) REFERENCES failure_reason_set (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE store ADD failure_reason_set_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE store ADD CONSTRAINT FK_FF575877CC1689AF FOREIGN KEY (failure_reason_set_id) REFERENCES failure_reason_set (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_FF575877CC1689AF ON store (failure_reason_set_id)');
        $this->addSql('ALTER TABLE task ADD failure_reason VARCHAR(32) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE failure_reason DROP CONSTRAINT FK_902D57448B51FD88');
        $this->addSql('ALTER TABLE store DROP CONSTRAINT FK_FF575877CC1689AF');
        $this->addSql('DROP TABLE failure_reason');
        $this->addSql('DROP TABLE failure_reason_set');
        $this->addSql('ALTER TABLE task DROP failure_reason');
        $this->addSql('DROP INDEX IDX_FF575877CC1689AF');
        $this->addSql('ALTER TABLE store DROP failure_reason_set_id');
    }
}
