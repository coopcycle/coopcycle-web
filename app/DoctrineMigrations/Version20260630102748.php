<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260630102748 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shopify_shop DROP shipping_rate_handle');
        $this->addSql('ALTER TABLE shopify_shop DROP postal_codes');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shopify_shop ADD shipping_rate_handle VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE shopify_shop ADD postal_codes JSON DEFAULT NULL');
    }
}
