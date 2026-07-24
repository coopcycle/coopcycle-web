<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260710141727 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add break_minutes to shift';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shift ADD break_minutes INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shift DROP break_minutes');
    }
}
