<?php

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171007174720 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE menu_category (id SERIAL NOT NULL, description VARCHAR(255) DEFAULT NULL, name VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('INSERT INTO menu_category (id, name) SELECT id, name FROM menu_section_base');

        $this->addSql('ALTER TABLE menu_section DROP CONSTRAINT fk_a5a86751f98e57a8');
        $this->addSql('DROP SEQUENCE menu_section_base_id_seq CASCADE');
        $this->addSql('DROP TABLE menu_section_base');
        $this->addSql('DROP INDEX idx_a5a86751f98e57a8');
        $this->addSql('ALTER TABLE menu_section RENAME COLUMN menu_section_id TO menu_category_id');
        $this->addSql('ALTER TABLE menu_section ADD CONSTRAINT FK_A5A867517ABA83AE FOREIGN KEY (menu_category_id) REFERENCES menu_category (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_A5A867517ABA83AE ON menu_section (menu_category_id)');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE menu_section DROP CONSTRAINT FK_A5A867517ABA83AE');
        $this->addSql('CREATE SEQUENCE menu_section_base_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE menu_section_base (id SERIAL NOT NULL, description VARCHAR(255) DEFAULT NULL, name VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('DROP TABLE menu_category');
        $this->addSql('DROP INDEX IDX_A5A867517ABA83AE');
        $this->addSql('ALTER TABLE menu_section RENAME COLUMN menu_category_id TO menu_section_id');
        $this->addSql('ALTER TABLE menu_section ADD CONSTRAINT fk_a5a86751f98e57a8 FOREIGN KEY (menu_section_id) REFERENCES menu_section_base (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_a5a86751f98e57a8 ON menu_section (menu_section_id)');
    }
}
