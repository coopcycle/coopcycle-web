<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260714093218 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add shift_time_adjustment (actual worked time reported on a shift assignment)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE shift_time_adjustment (id SERIAL NOT NULL, assignment_id INT NOT NULL, reported_by_id INT DEFAULT NULL, starts_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, ends_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, break_minutes INT DEFAULT 0 NOT NULL, comment TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_7BCF79C8D19302F8 ON shift_time_adjustment (assignment_id)');
        $this->addSql('CREATE INDEX IDX_7BCF79C871CE806 ON shift_time_adjustment (reported_by_id)');
        $this->addSql('ALTER TABLE shift_time_adjustment ADD CONSTRAINT FK_7BCF79C8D19302F8 FOREIGN KEY (assignment_id) REFERENCES shift_assignment (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE shift_time_adjustment ADD CONSTRAINT FK_7BCF79C871CE806 FOREIGN KEY (reported_by_id) REFERENCES api_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shift_time_adjustment DROP CONSTRAINT FK_7BCF79C8D19302F8');
        $this->addSql('ALTER TABLE shift_time_adjustment DROP CONSTRAINT FK_7BCF79C871CE806');
        $this->addSql('DROP TABLE shift_time_adjustment');
    }
}
