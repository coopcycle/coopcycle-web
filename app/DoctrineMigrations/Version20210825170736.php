<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210825170736 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE store ADD multi_drop_enabled BOOLEAN DEFAULT NULL');
        $this->addSql('UPDATE store SET multi_drop_enabled = \'f\'');
        $this->addSql('ALTER TABLE store ALTER multi_drop_enabled SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE store DROP multi_drop_enabled');
    }
}
