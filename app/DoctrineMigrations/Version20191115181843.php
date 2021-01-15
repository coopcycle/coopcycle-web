<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191115181843 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE sylius_product_variant_option_value DROP CONSTRAINT sylius_product_variant_option_value_pkey');
        $this->addSql('CREATE UNIQUE INDEX sylius_product_variant_option_value_unique ON sylius_product_variant_option_value (variant_id, option_value_id)');

        $this->addSql('ALTER TABLE sylius_product_variant_option_value ADD id SERIAL NOT NULL');
        $this->addSql('ALTER TABLE sylius_product_variant_option_value ADD PRIMARY KEY (id)');

        $this->addSql('ALTER TABLE sylius_product_variant_option_value ADD quantity INT DEFAULT NULL');
        $this->addSql('UPDATE sylius_product_variant_option_value SET quantity = 1');
        $this->addSql('ALTER TABLE sylius_product_variant_option_value ALTER COLUMN quantity SET NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE sylius_product_variant_option_value DROP quantity');

        $this->addSql('ALTER TABLE sylius_product_variant_option_value DROP CONSTRAINT sylius_product_variant_option_value_pkey');
        $this->addSql('ALTER TABLE sylius_product_variant_option_value DROP id');

        $this->addSql('DROP INDEX sylius_product_variant_option_value_unique');

        $this->addSql('ALTER TABLE sylius_product_variant_option_value ADD PRIMARY KEY (variant_id, option_value_id)');
    }
}
