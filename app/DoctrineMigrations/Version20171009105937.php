<?php

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171009105937 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE menu_section ADD name VARCHAR(255) DEFAULT NULL');
        $this->addSql('UPDATE menu_section SET name = menu_category.name FROM menu_category WHERE menu_category.id = menu_section.menu_category_id');

        $this->addSql('ALTER TABLE menu_section DROP CONSTRAINT fk_a5a867517aba83ae');
        $this->addSql('DROP INDEX idx_a5a867517aba83ae');
        $this->addSql('ALTER TABLE menu_section DROP menu_category_id');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE menu_section ADD menu_category_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE menu_section DROP name');
        $this->addSql('ALTER TABLE menu_section ADD CONSTRAINT fk_a5a867517aba83ae FOREIGN KEY (menu_category_id) REFERENCES menu_category (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_a5a867517aba83ae ON menu_section (menu_category_id)');
    }
}
