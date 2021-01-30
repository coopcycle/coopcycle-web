<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210130173627 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE sylius_product_variant_configuration (id SERIAL NOT NULL, variant_id INT NOT NULL, configuration JSON NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_66BC7A3C3B69A9AF ON sylius_product_variant_configuration (variant_id)');
        $this->addSql('COMMENT ON COLUMN sylius_product_variant_configuration.configuration IS \'(DC2Type:json_array)\'');
        $this->addSql('ALTER TABLE sylius_product_variant_configuration ADD CONSTRAINT FK_66BC7A3C3B69A9AF FOREIGN KEY (variant_id) REFERENCES sylius_product_variant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_product_variant ADD configuration_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_product_variant ADD CONSTRAINT FK_A29B52373F32DD8 FOREIGN KEY (configuration_id) REFERENCES sylius_product_variant_configuration (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_A29B52373F32DD8 ON sylius_product_variant (configuration_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE sylius_product_variant DROP CONSTRAINT FK_A29B52373F32DD8');
        $this->addSql('DROP TABLE sylius_product_variant_configuration');
        $this->addSql('DROP INDEX IDX_A29B52373F32DD8');
        $this->addSql('ALTER TABLE sylius_product_variant DROP configuration_id');
    }
}
