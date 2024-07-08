<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240704153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add a new system tag: __bookmark';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('INSERT INTO tag (name, slug, color, created_at, updated_at) VALUES (\'__bookmark\', \'__bookmark\', \'#ffffff\', NOW(), NOW())');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM tag WHERE slug = \'__bookmark\'');
    }
}
