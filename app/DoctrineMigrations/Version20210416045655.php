<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210416045655 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql('CREATE INDEX IDX_377B6C63AA9E377A ON task_list (date)');
    }

    public function down(Schema $schema) : void
    {
        $this->addSql('DROP INDEX IDX_377B6C63AA9E377A');
    }
}
