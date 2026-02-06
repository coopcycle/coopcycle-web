<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260129084005 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add icon field to cuisine';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cuisine ADD icon VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cuisine DROP icon');
    }
}
