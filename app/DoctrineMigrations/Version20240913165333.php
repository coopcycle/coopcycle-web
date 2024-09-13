<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240913165333 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add billingMethod field';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE sylius_order ADD COLUMN billing_method VARCHAR(255) NOT NULL DEFAULT 'per_task'");
        $this->addSql("ALTER TABLE store ADD COLUMN billing_method VARCHAR(255) NOT NULL DEFAULT 'per_task'");
        $this->addSql("ALTER TABLE restaurant ADD COLUMN billing_method VARCHAR(255) NOT NULL DEFAULT 'per_task'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE sylius_order DROP COLUMN billing_method");
        $this->addSql("ALTER TABLE store DROP COLUMN billing_method");
        $this->addSql("ALTER TABLE restaurant DROP COLUMN billing_method");
    }
}
