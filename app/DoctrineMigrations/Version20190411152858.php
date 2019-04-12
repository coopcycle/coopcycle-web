<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20190411152858 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE sylius_product_option_value ADD tax_category_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_product_option_value ADD CONSTRAINT FK_F7FF7D4B9DF894ED FOREIGN KEY (tax_category_id) REFERENCES sylius_tax_category (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_F7FF7D4B9DF894ED ON sylius_product_option_value (tax_category_id)');

        // TODO Set default tax category?
    }

    public function down(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE sylius_product_option_value DROP CONSTRAINT FK_F7FF7D4B9DF894ED');
        $this->addSql('DROP INDEX IDX_F7FF7D4B9DF894ED');
        $this->addSql('ALTER TABLE sylius_product_option_value DROP tax_category_id');
    }
}
