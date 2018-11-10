<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20181110120507 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE store_delivery (store_id INT NOT NULL, delivery_id INT NOT NULL, PRIMARY KEY(store_id, delivery_id))');
        $this->addSql('CREATE INDEX IDX_9F693EAB092A811 ON store_delivery (store_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9F693EA12136921 ON store_delivery (delivery_id)');
        $this->addSql('ALTER TABLE store_delivery ADD CONSTRAINT FK_9F693EAB092A811 FOREIGN KEY (store_id) REFERENCES store (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE store_delivery ADD CONSTRAINT FK_9F693EA12136921 FOREIGN KEY (delivery_id) REFERENCES delivery (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE store_delivery');
    }
}
