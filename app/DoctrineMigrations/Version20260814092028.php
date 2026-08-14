<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260814092028 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move shift activity colors from the shift_type_colors setting onto shift_activity.color';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shift_activity ADD color VARCHAR(7) DEFAULT NULL');
        $this->addSql("UPDATE shift_activity SET color = (SELECT value::json->>shift_activity.slug FROM craue_config_setting WHERE name = 'shift_type_colors' LIMIT 1)");
        $this->addSql("DELETE FROM craue_config_setting WHERE name = 'shift_type_colors'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("INSERT INTO craue_config_setting (name, section, value) SELECT 'shift_type_colors', NULL, COALESCE((SELECT json_object_agg(slug, color)::text FROM shift_activity WHERE color IS NOT NULL), '{}')");
        $this->addSql('ALTER TABLE shift_activity DROP color');
    }
}
