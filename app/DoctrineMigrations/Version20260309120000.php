<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260309120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add metadata column to sylius_product_option_value for Zelty menu integration';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_product_option_value ADD metadata JSONB DEFAULT \'{}\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_product_option_value DROP COLUMN metadata');
    }
}
