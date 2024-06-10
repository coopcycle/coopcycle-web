<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180516124244 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE restaurant ADD active_menu_taxon_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE restaurant ADD CONSTRAINT FK_EB95123F2C187544 FOREIGN KEY (active_menu_taxon_id) REFERENCES sylius_taxon (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_EB95123F2C187544 ON restaurant (active_menu_taxon_id)');

        $this->stmt = [];
        $this->stmt['restaurant_taxon'] = $this->connection->prepare('SELECT * FROM restaurant_taxon');
        $result = $this->stmt['restaurant_taxon']->execute();

        while ($restaurantTaxon = $result->fetchAssociative()) {
            $this->addSql('UPDATE restaurant SET active_menu_taxon_id = :taxon_id WHERE id = :restaurant_id', $restaurantTaxon);
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE restaurant DROP CONSTRAINT FK_EB95123F2C187544');
        $this->addSql('DROP INDEX IDX_EB95123F2C187544');
        $this->addSql('ALTER TABLE restaurant DROP active_menu_taxon_id');
    }
}
