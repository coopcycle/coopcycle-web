<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20180821085532 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE sylius_order_event (id SERIAL NOT NULL, aggregate_id INT DEFAULT NULL, type VARCHAR(255) NOT NULL, data JSON NOT NULL, metadata JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_1F7207A3D0BBCCBE ON sylius_order_event (aggregate_id)');
        $this->addSql('COMMENT ON COLUMN sylius_order_event.data IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN sylius_order_event.metadata IS \'(DC2Type:json_array)\'');
        $this->addSql('ALTER TABLE sylius_order_event ADD CONSTRAINT FK_1F7207A3D0BBCCBE FOREIGN KEY (aggregate_id) REFERENCES sylius_order (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE sylius_order_event');
    }
}
