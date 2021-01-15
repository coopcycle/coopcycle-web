<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180315023248 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE stripe_payment');

        $this->addSql('CREATE TABLE stripe_payment (id SERIAL NOT NULL, order_id INT DEFAULT NULL, charge VARCHAR(255) DEFAULT NULL, currency_code VARCHAR(255) NOT NULL, amount INT NOT NULL, state VARCHAR(255) NOT NULL, details JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_42EFB5F78D9F6D38 ON stripe_payment (order_id)');
        $this->addSql('COMMENT ON COLUMN stripe_payment.details IS \'(DC2Type:json_array)\'');
        $this->addSql('ALTER TABLE stripe_payment ADD CONSTRAINT FK_42EFB5F78D9F6D38 FOREIGN KEY (order_id) REFERENCES sylius_order (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');
    }
}
