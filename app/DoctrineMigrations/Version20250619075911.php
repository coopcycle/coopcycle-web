<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250619075911 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE store ADD multi_pickup_enabled BOOLEAN DEFAULT NULL
        SQL);

        $this->addSql(<<<'SQL'
            UPDATE store SET multi_pickup_enabled = 'f'
        SQL);


        $this->addSql('ALTER TABLE store ALTER COLUMN multi_pickup_enabled SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE store DROP multi_pickup_enabled
        SQL);
    }
}
