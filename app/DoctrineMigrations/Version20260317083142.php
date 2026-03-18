<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260317083142 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_product_option_value ADD product_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_product_option_value ADD CONSTRAINT FK_F7FF7D4B4584665A FOREIGN KEY (product_id) REFERENCES sylius_product (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_F7FF7D4B4584665A ON sylius_product_option_value (product_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_product_option_value DROP CONSTRAINT FK_F7FF7D4B4584665A');
        $this->addSql('DROP INDEX IDX_F7FF7D4B4584665A');
        $this->addSql('ALTER TABLE sylius_product_option_value DROP product_id');
    }
}
