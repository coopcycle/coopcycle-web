<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180511084515 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE sylius_product_taxon (id SERIAL NOT NULL, product_id INT NOT NULL, taxon_id INT NOT NULL, position INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_169C6CD94584665A ON sylius_product_taxon (product_id)');
        $this->addSql('CREATE INDEX IDX_169C6CD9DE13F470 ON sylius_product_taxon (taxon_id)');
        $this->addSql('CREATE UNIQUE INDEX sylius_product_taxon_unique ON sylius_product_taxon (product_id, taxon_id)');
        $this->addSql('ALTER TABLE sylius_product_taxon ADD CONSTRAINT FK_169C6CD94584665A FOREIGN KEY (product_id) REFERENCES sylius_product (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_product_taxon ADD CONSTRAINT FK_169C6CD9DE13F470 FOREIGN KEY (taxon_id) REFERENCES sylius_taxon (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE sylius_product_taxon');
    }
}
