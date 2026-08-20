<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds a kill switch to stop sending orders to Zelty.
 */
final class Version20260820090701 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add restaurant.zelty_orders_enabled';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE restaurant ADD zelty_orders_enabled BOOLEAN DEFAULT true NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE restaurant DROP zelty_orders_enabled');
    }
}
