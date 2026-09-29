<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260929150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add task_image.type (signature or photo, null when the client did not tell)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task_image ADD type VARCHAR(16) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task_image DROP type');
    }
}
