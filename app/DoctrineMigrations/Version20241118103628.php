<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241118103628 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        // create a tag for to flag tasks created with invalid addresses
        // soon to be replaced by an incident
        $this->addSql("INSERT INTO tag ('name', slug, color, created_at, updated_at)
            SELECT 'review-needed', 'review-needed', '#e42b2b', NOW(), NOW()
            WHERE NOT EXISTS (SELECT 'sluc' FROM tag WHERE 'sluc' = 'review-needed'
        ");

    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
