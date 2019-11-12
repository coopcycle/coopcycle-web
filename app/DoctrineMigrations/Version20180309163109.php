<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180309163109 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE stripe_payment (id SERIAL NOT NULL, user_id INT DEFAULT NULL, uuid VARCHAR(255) NOT NULL, resource_class VARCHAR(255) NOT NULL, resource_id INT NOT NULL, status VARCHAR(255) NOT NULL, charge VARCHAR(255) DEFAULT NULL, total_excluding_tax DOUBLE PRECISION NOT NULL, total_tax DOUBLE PRECISION NOT NULL, total_including_tax DOUBLE PRECISION NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_42EFB5F7A76ED395 ON stripe_payment (user_id)');
        $this->addSql('CREATE UNIQUE INDEX stripe_payment_unique ON stripe_payment (resource_class, resource_id)');
        $this->addSql('ALTER TABLE stripe_payment ADD CONSTRAINT FK_42EFB5F7A76ED395 FOREIGN KEY (user_id) REFERENCES api_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE stripe_payment');
    }
}
