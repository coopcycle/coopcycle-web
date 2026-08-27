<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Shops can now choose which Zelty transaction method their payments are recorded
 * under. NULL keeps the previous behaviour ("CB").
 */
final class Version20260827091500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add restaurant.zelty_transaction_method';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE restaurant ADD zelty_transaction_method VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE restaurant DROP zelty_transaction_method');
    }
}
