<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210723161106 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_527edb25bc2d6b55');
        $this->addSql('CREATE INDEX IDX_527EDB25BC2D6B55 ON task (previous_task_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_527EDB25BC2D6B55');
        $this->addSql('CREATE UNIQUE INDEX uniq_527edb25bc2d6b55 ON task (previous_task_id)');
    }
}
