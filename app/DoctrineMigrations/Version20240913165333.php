<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240913165333 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add billing method field';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE store ADD COLUMN billing_method VARCHAR(255) NOT NULL DEFAULT 'unit'");
        $this->addSql("ALTER TABLE restaurant ADD COLUMN billing_method VARCHAR(255) NOT NULL DEFAULT 'unit'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE store DROP COLUMN billing_method");
        $this->addSql("ALTER TABLE restaurant DROP COLUMN billing_method");
    }
}
