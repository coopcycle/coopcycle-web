<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260915093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add external_reference to delivery (distinct from the per-task barcode)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE delivery ADD external_reference VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE delivery DROP external_reference');
    }
}
