<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260909094820 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add sylius_product_image.zelty_checksum, used to detect when a Zelty-synced image changed.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_product_image ADD zelty_checksum VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_product_image DROP zelty_checksum');
    }
}
