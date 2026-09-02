<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260902074944 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ShiftActivity::addToDispatch, controlling whether couriers assigned to a shift with that activity are added to the dispatch; on by default for the shipped "delivery" activity only';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shift_activity ADD add_to_dispatch BOOLEAN DEFAULT false NOT NULL');
        $this->addSql("UPDATE shift_activity SET add_to_dispatch = true WHERE slug = 'delivery'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shift_activity DROP add_to_dispatch');
    }
}
