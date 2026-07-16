<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260715160014 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add store.cyke_time_slot';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE store ADD cyke_time_slot VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE store DROP cyke_time_slot');
    }
}
