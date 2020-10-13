<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201020102523 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    private function createVendors()
    {
        $stmt = $this->connection->prepare('SELECT DISTINCT restaurant_id FROM sylius_order WHERE restaurant_id IS NOT NULL');
        $stmt->execute();

        while ($order = $stmt->fetch()) {
            $this->addSql('INSERT INTO vendor (restaurant_id) VALUES (:restaurant_id)', [
                'restaurant_id' => $order['restaurant_id'],
            ]);
        }
    }

    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->createVendors();

        $this->addSql('ALTER TABLE sylius_order ADD vendor_id INT DEFAULT NULL');
        $this->addSql('UPDATE sylius_order o SET vendor_id = v.id FROM vendor v WHERE o.restaurant_id IS NOT NULL AND o.restaurant_id = v.restaurant_id');

        $this->addSql('ALTER TABLE sylius_order DROP CONSTRAINT fk_6196a1f9b1e7706e');
        $this->addSql('DROP INDEX idx_6196a1f9b1e7706e');
        $this->addSql('ALTER TABLE sylius_order DROP restaurant_id');

        $this->addSql('ALTER TABLE sylius_order ADD CONSTRAINT FK_6196A1F9F603EE73 FOREIGN KEY (vendor_id) REFERENCES vendor (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_6196A1F9F603EE73 ON sylius_order (vendor_id)');
    }

    public function down(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE sylius_order ADD restaurant_id INT DEFAULT NULL');
        $this->addSql('UPDATE sylius_order o SET restaurant_id = v.restaurant_id FROM vendor v WHERE o.vendor_id IS NOT NULL AND o.vendor_id = v.id');

        $this->addSql('ALTER TABLE sylius_order DROP CONSTRAINT FK_6196A1F9F603EE73');
        $this->addSql('DROP INDEX IDX_6196A1F9F603EE73');
        $this->addSql('ALTER TABLE sylius_order DROP vendor_id');

        $this->addSql('ALTER TABLE sylius_order ADD CONSTRAINT fk_6196a1f9b1e7706e FOREIGN KEY (restaurant_id) REFERENCES restaurant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_6196a1f9b1e7706e ON sylius_order (restaurant_id)');

        $this->addSql('DELETE FROM vendor');
    }
}
