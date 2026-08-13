<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260813164552 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add shift_template / shift_template_shift (reusable shift-week templates)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE shift_template (id SERIAL NOT NULL, created_by_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_BED48727B03A8386 ON shift_template (created_by_id)');
        $this->addSql('CREATE TABLE shift_template_shift (id SERIAL NOT NULL, template_id INT NOT NULL, type VARCHAR(32) NOT NULL, day_of_week SMALLINT NOT NULL, start_time TIME(0) WITHOUT TIME ZONE NOT NULL, end_time TIME(0) WITHOUT TIME ZONE NOT NULL, slots INT DEFAULT 1 NOT NULL, break_minutes INT DEFAULT 0 NOT NULL, comment TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_50AA73015DA0FB8 ON shift_template_shift (template_id)');
        $this->addSql('CREATE TABLE shift_template_shift_required_skill (shift_template_shift_id INT NOT NULL, skill_id INT NOT NULL, PRIMARY KEY(shift_template_shift_id, skill_id))');
        $this->addSql('CREATE INDEX IDX_6ED2BDF15592CF94 ON shift_template_shift_required_skill (shift_template_shift_id)');
        $this->addSql('CREATE INDEX IDX_6ED2BDF15585C142 ON shift_template_shift_required_skill (skill_id)');
        $this->addSql('CREATE TABLE shift_template_shift_user (shift_template_shift_id INT NOT NULL, user_id INT NOT NULL, PRIMARY KEY(shift_template_shift_id, user_id))');
        $this->addSql('CREATE INDEX IDX_FA0805725592CF94 ON shift_template_shift_user (shift_template_shift_id)');
        $this->addSql('CREATE INDEX IDX_FA080572A76ED395 ON shift_template_shift_user (user_id)');
        $this->addSql('ALTER TABLE shift_template ADD CONSTRAINT FK_BED48727B03A8386 FOREIGN KEY (created_by_id) REFERENCES api_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE shift_template_shift ADD CONSTRAINT FK_50AA73015DA0FB8 FOREIGN KEY (template_id) REFERENCES shift_template (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE shift_template_shift_required_skill ADD CONSTRAINT FK_6ED2BDF15592CF94 FOREIGN KEY (shift_template_shift_id) REFERENCES shift_template_shift (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE shift_template_shift_required_skill ADD CONSTRAINT FK_6ED2BDF15585C142 FOREIGN KEY (skill_id) REFERENCES skill (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE shift_template_shift_user ADD CONSTRAINT FK_FA0805725592CF94 FOREIGN KEY (shift_template_shift_id) REFERENCES shift_template_shift (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE shift_template_shift_user ADD CONSTRAINT FK_FA080572A76ED395 FOREIGN KEY (user_id) REFERENCES api_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shift_template DROP CONSTRAINT FK_BED48727B03A8386');
        $this->addSql('ALTER TABLE shift_template_shift DROP CONSTRAINT FK_50AA73015DA0FB8');
        $this->addSql('ALTER TABLE shift_template_shift_required_skill DROP CONSTRAINT FK_6ED2BDF15592CF94');
        $this->addSql('ALTER TABLE shift_template_shift_required_skill DROP CONSTRAINT FK_6ED2BDF15585C142');
        $this->addSql('ALTER TABLE shift_template_shift_user DROP CONSTRAINT FK_FA0805725592CF94');
        $this->addSql('ALTER TABLE shift_template_shift_user DROP CONSTRAINT FK_FA080572A76ED395');
        $this->addSql('DROP TABLE shift_template_shift_user');
        $this->addSql('DROP TABLE shift_template_shift_required_skill');
        $this->addSql('DROP TABLE shift_template_shift');
        $this->addSql('DROP TABLE shift_template');
    }
}
