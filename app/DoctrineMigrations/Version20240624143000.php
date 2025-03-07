<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240624143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Soft delete organizations that are linked to a soft deleted restaurant or store';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('
            UPDATE organization SET deleted_at = NOW()
            WHERE
                id IN (select organization_id from restaurant where deleted_at is not null) OR
                id IN (select organization_id from store where deleted_at is not null)
        ');

    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
