<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200914105344 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    private function createOrganizationsForRestaurants()
    {
        $stmt = $this->connection->prepare('SELECT id, name FROM restaurant');
        $result = $stmt->execute();
        while ($restaurant = $result->fetchAssociative()) {
            $this->addSql('INSERT INTO organization (name) VALUES (:name)', $restaurant);
            $this->addSql('UPDATE restaurant SET organization_id = currval(\'organization_id_seq\') WHERE id = :restaurant_id', [
                'restaurant_id' => $restaurant['id']
            ]);
        }
    }

    private function createOrganizationsForStores()
    {
        $stmt = $this->connection->prepare('SELECT id, name FROM store');
        $result = $stmt->execute();
        while ($store = $result->fetchAssociative()) {
            $this->addSql('INSERT INTO organization (name) VALUES (:name)', $store);
            $this->addSql('UPDATE store SET organization_id = currval(\'organization_id_seq\') WHERE id = :store_id', [
                'store_id' => $store['id']
            ]);
        }
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE restaurant ADD organization_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE store ADD organization_id INT DEFAULT NULL');

        $this->addSql('ALTER TABLE restaurant ADD CONSTRAINT FK_EB95123F32C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_EB95123F32C8A3DE ON restaurant (organization_id)');
        $this->addSql('ALTER TABLE store ADD CONSTRAINT FK_FF57587732C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_FF57587732C8A3DE ON store (organization_id)');

        $this->createOrganizationsForRestaurants();
        $this->createOrganizationsForStores();

        $this->addSql('ALTER TABLE restaurant ALTER organization_id SET NOT NULL');
        $this->addSql('ALTER TABLE store ALTER organization_id SET NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DELETE FROM organization WHERE id IN (SELECT organization_id FROM restaurant)');
        $this->addSql('DELETE FROM organization WHERE id IN (SELECT organization_id FROM store)');

        $this->addSql('ALTER TABLE store DROP CONSTRAINT FK_FF57587732C8A3DE');
        $this->addSql('DROP INDEX IDX_FF57587732C8A3DE');
        $this->addSql('ALTER TABLE store DROP organization_id');
        $this->addSql('ALTER TABLE restaurant DROP CONSTRAINT FK_EB95123F32C8A3DE');
        $this->addSql('DROP INDEX IDX_EB95123F32C8A3DE');
        $this->addSql('ALTER TABLE restaurant DROP organization_id');
        $this->addSql('ALTER TABLE restaurant ALTER is_available_for_b2b SET DEFAULT \'false\'');
    }
}
