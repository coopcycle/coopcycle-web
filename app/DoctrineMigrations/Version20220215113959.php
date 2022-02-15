<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220215113959 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('COMMENT ON COLUMN sylius_payment.details IS NULL');
        $this->addSql('COMMENT ON COLUMN sylius_product_attribute_value.json_value IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('COMMENT ON COLUMN sylius_payment.details IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN sylius_product_attribute_value.json_value IS \'(DC2Type:json_array)\'');
    }
}
