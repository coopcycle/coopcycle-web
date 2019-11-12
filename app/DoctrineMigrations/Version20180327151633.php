<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180327151633 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE sylius_product_variant ADD tax_category_id INT NOT NULL');
        $this->addSql('ALTER TABLE sylius_product_variant ADD price INT NOT NULL');
        $this->addSql('ALTER TABLE sylius_product_variant ADD CONSTRAINT FK_A29B5239DF894ED FOREIGN KEY (tax_category_id) REFERENCES sylius_tax_category (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_A29B5239DF894ED ON sylius_product_variant (tax_category_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE sylius_product_variant DROP CONSTRAINT FK_A29B5239DF894ED');
        $this->addSql('DROP INDEX IDX_A29B5239DF894ED');
        $this->addSql('ALTER TABLE sylius_product_variant DROP tax_category_id');
        $this->addSql('ALTER TABLE sylius_product_variant DROP price');
    }
}
