<?php

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170807082914 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE order_item ADD menu_item_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE order_item ADD name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE order_item ADD price DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_52EA1F099AB44FE0 FOREIGN KEY (menu_item_id) REFERENCES menu_item (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_52EA1F099AB44FE0 ON order_item (menu_item_id)');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE order_item DROP CONSTRAINT FK_52EA1F099AB44FE0');
        $this->addSql('DROP INDEX IDX_52EA1F099AB44FE0');
        $this->addSql('ALTER TABLE order_item DROP menu_item_id');
        $this->addSql('ALTER TABLE order_item DROP name');
        $this->addSql('ALTER TABLE order_item DROP price');
    }
}
