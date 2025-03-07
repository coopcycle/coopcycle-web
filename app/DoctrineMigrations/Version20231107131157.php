<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231107131157 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Updates orders state when delivery\'s tasks are cancelled';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
        UPDATE sylius_order
        SET state = 'cancelled'
        WHERE id IN (
            SELECT order_id FROM delivery d
            WHERE NOT EXISTS (
                SELECT 1
                FROM task t
                WHERE d.id = t.delivery_id
                AND t.status != 'CANCELLED'
            ) AND d.order_id IS NOT NULL
        );
    ");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
