<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180510150849 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE restaurant_taxon (restaurant_id INT NOT NULL, taxon_id INT NOT NULL, PRIMARY KEY(restaurant_id, taxon_id))');
        $this->addSql('CREATE INDEX IDX_117A907AB1E7706E ON restaurant_taxon (restaurant_id)');
        $this->addSql('CREATE INDEX IDX_117A907ADE13F470 ON restaurant_taxon (taxon_id)');
        $this->addSql('ALTER TABLE restaurant_taxon ADD CONSTRAINT FK_117A907AB1E7706E FOREIGN KEY (restaurant_id) REFERENCES restaurant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE restaurant_taxon ADD CONSTRAINT FK_117A907ADE13F470 FOREIGN KEY (taxon_id) REFERENCES sylius_taxon (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE restaurant_taxon');
    }
}
