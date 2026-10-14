<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Allows soft deleting product option values, so they remain visible on previous orders.
 */
final class Version20261009090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add deleted_at column on sylius_product_option_value';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_product_option_value ADD deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_product_option_value DROP deleted_at');
    }
}
