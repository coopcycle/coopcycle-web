<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251010114809 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_product_options ADD enabled BOOLEAN DEFAULT NULL');
        $this->addSql('UPDATE sylius_product_options SET enabled = \'t\'');
        $this->addSql('ALTER TABLE sylius_product_options ALTER COLUMN enabled SET NOT NULL');

    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_product_options DROP enabled');
    }
}
