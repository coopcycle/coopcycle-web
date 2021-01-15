<?php

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171130231943 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE menu_item ADD tax_category_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE menu_item ADD CONSTRAINT FK_D754D5509DF894ED FOREIGN KEY (tax_category_id) REFERENCES sylius_tax_category (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_D754D5509DF894ED ON menu_item (tax_category_id)');
        $this->addSql('ALTER TABLE modifier ADD tax_category_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE modifier ADD CONSTRAINT FK_ABBFD9FD9DF894ED FOREIGN KEY (tax_category_id) REFERENCES sylius_tax_category (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_ABBFD9FD9DF894ED ON modifier (tax_category_id)');

        $this->addSql("UPDATE menu_item SET tax_category_id = (SELECT id FROM sylius_tax_category where code = 'tva_conso_immediate')");
        $this->addSql("UPDATE modifier SET tax_category_id = (SELECT id FROM sylius_tax_category where code = 'tva_conso_immediate')");

        $this->addSql('ALTER TABLE menu_item ALTER COLUMN tax_category_id SET NOT NULL');
        $this->addSql('ALTER TABLE modifier ALTER COLUMN tax_category_id SET NOT NULL');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE menu_item DROP CONSTRAINT FK_D754D5509DF894ED');
        $this->addSql('DROP INDEX IDX_D754D5509DF894ED');
        $this->addSql('ALTER TABLE menu_item DROP tax_category_id');
        $this->addSql('ALTER TABLE modifier DROP CONSTRAINT FK_ABBFD9FD9DF894ED');
        $this->addSql('DROP INDEX IDX_ABBFD9FD9DF894ED');
        $this->addSql('ALTER TABLE modifier DROP tax_category_id');
    }
}
