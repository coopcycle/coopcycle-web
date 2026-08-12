<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260709155540 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add zelty_delivery_fee_dish_id to restaurant';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE restaurant ADD zelty_delivery_fee_dish_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE restaurant DROP zelty_delivery_fee_dish_id');
    }
}
