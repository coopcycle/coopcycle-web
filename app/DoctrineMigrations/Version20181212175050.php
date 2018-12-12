<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20181212175050 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE store ADD prefill_pickup_address BOOLEAN DEFAULT NULL');
        $this->addSql('ALTER TABLE store ADD create_orders BOOLEAN DEFAULT NULL');

        $this->addSql('UPDATE store SET prefill_pickup_address = \'f\'');
        $this->addSql('UPDATE store SET create_orders = \'f\'');

        $this->addSql('ALTER TABLE store ALTER prefill_pickup_address SET NOT NULL');
        $this->addSql('ALTER TABLE store ALTER create_orders SET NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE store DROP prefill_pickup_address');
        $this->addSql('ALTER TABLE store DROP create_orders');
    }
}
