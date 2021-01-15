<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171224000934 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE api_user_store (api_user_id INT NOT NULL, store_id INT NOT NULL, PRIMARY KEY(api_user_id, store_id))');
        $this->addSql('CREATE INDEX IDX_DA3FDF3E4A50A7F2 ON api_user_store (api_user_id)');
        $this->addSql('CREATE INDEX IDX_DA3FDF3EB092A811 ON api_user_store (store_id)');
        $this->addSql('CREATE TABLE store (id SERIAL NOT NULL, address_id INT DEFAULT NULL, stripe_params_id INT DEFAULT NULL, name VARCHAR(255) DEFAULT NULL, enabled BOOLEAN DEFAULT \'false\' NOT NULL, image_name VARCHAR(255) DEFAULT NULL, website VARCHAR(255) DEFAULT NULL, telephone VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, legal_name VARCHAR(255) DEFAULT NULL, opening_hours JSON DEFAULT NULL, vat_id VARCHAR(255) DEFAULT NULL, additional_properties JSON DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FF575877F5B7AF75 ON store (address_id)');
        $this->addSql('CREATE INDEX IDX_FF5758778C11ECC5 ON store (stripe_params_id)');
        $this->addSql('COMMENT ON COLUMN store.opening_hours IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN store.additional_properties IS \'(DC2Type:json_array)\'');
        $this->addSql('ALTER TABLE api_user_store ADD CONSTRAINT FK_DA3FDF3E4A50A7F2 FOREIGN KEY (api_user_id) REFERENCES api_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE api_user_store ADD CONSTRAINT FK_DA3FDF3EB092A811 FOREIGN KEY (store_id) REFERENCES store (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE store ADD CONSTRAINT FK_FF575877F5B7AF75 FOREIGN KEY (address_id) REFERENCES address (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE store ADD CONSTRAINT FK_FF5758778C11ECC5 FOREIGN KEY (stripe_params_id) REFERENCES stripe_params (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE api_user_store DROP CONSTRAINT FK_DA3FDF3EB092A811');
        $this->addSql('DROP TABLE api_user_store');
        $this->addSql('DROP TABLE store');
    }
}
