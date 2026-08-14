<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260814082433 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add shift_activity catalog; rename shift/shift_template_shift "type" to "activity" (drive -> delivery, admin -> administration)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE shift_activity (id SERIAL NOT NULL, slug VARCHAR(64) NOT NULL, label VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX shift_activity_slug_unique ON shift_activity (slug)');
        $this->addSql("INSERT INTO shift_activity (slug, label, created_at, updated_at) VALUES ('delivery', 'Delivery', NOW(), NOW())");
        $this->addSql("INSERT INTO shift_activity (slug, label, created_at, updated_at) VALUES ('dispatch', 'Dispatch', NOW(), NOW())");
        $this->addSql("INSERT INTO shift_activity (slug, label, created_at, updated_at) VALUES ('administration', 'Administration', NOW(), NOW())");

        $this->addSql('ALTER TABLE shift RENAME COLUMN type TO activity');
        $this->addSql('ALTER TABLE shift ALTER COLUMN activity TYPE VARCHAR(64)');
        $this->addSql("UPDATE shift SET activity = 'delivery' WHERE activity = 'drive'");
        $this->addSql("UPDATE shift SET activity = 'administration' WHERE activity = 'admin'");

        $this->addSql('ALTER TABLE shift_template_shift RENAME COLUMN type TO activity');
        $this->addSql('ALTER TABLE shift_template_shift ALTER COLUMN activity TYPE VARCHAR(64)');
        $this->addSql("UPDATE shift_template_shift SET activity = 'delivery' WHERE activity = 'drive'");
        $this->addSql("UPDATE shift_template_shift SET activity = 'administration' WHERE activity = 'admin'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE shift_template_shift SET activity = 'admin' WHERE activity = 'administration'");
        $this->addSql("UPDATE shift_template_shift SET activity = 'drive' WHERE activity = 'delivery'");
        $this->addSql('ALTER TABLE shift_template_shift ALTER COLUMN activity TYPE VARCHAR(32)');
        $this->addSql('ALTER TABLE shift_template_shift RENAME COLUMN activity TO type');

        $this->addSql("UPDATE shift SET activity = 'admin' WHERE activity = 'administration'");
        $this->addSql("UPDATE shift SET activity = 'drive' WHERE activity = 'delivery'");
        $this->addSql('ALTER TABLE shift ALTER COLUMN activity TYPE VARCHAR(32)');
        $this->addSql('ALTER TABLE shift RENAME COLUMN activity TO type');

        $this->addSql('DROP TABLE shift_activity');
    }
}
