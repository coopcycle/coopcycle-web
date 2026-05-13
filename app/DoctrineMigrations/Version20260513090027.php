<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260513090027 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Set homepage_published = t when ui_homepage_block is not empty';
    }

    public function up(Schema $schema): void
    {
        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM ui_homepage_block');

        if ($count > 0) {
            $this->addSql('INSERT INTO craue_config_setting (name, section, value) VALUES (:name, :section, :value)', [
                'name'    => 'homepage_published',
                'section' => 'general',
                'value'   => 't',
            ]);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM craue_config_setting WHERE name = :name', [
            'name' => 'homepage_published',
        ]);
    }
}
