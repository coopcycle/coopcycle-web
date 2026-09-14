<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260914104658 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add shift_preset (reusable single-shift templates)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE shift_preset (id SERIAL NOT NULL, created_by_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, activity VARCHAR(64) NOT NULL, start_time TIME(0) WITHOUT TIME ZONE NOT NULL, end_time TIME(0) WITHOUT TIME ZONE NOT NULL, slots INT DEFAULT 1 NOT NULL, break_minutes INT DEFAULT 0 NOT NULL, comment TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_A0877627B03A8386 ON shift_preset (created_by_id)');
        $this->addSql('CREATE TABLE shift_preset_required_skill (shift_preset_id INT NOT NULL, skill_id INT NOT NULL, PRIMARY KEY(shift_preset_id, skill_id))');
        $this->addSql('CREATE INDEX IDX_F091FC6F51FA5D06 ON shift_preset_required_skill (shift_preset_id)');
        $this->addSql('CREATE INDEX IDX_F091FC6F5585C142 ON shift_preset_required_skill (skill_id)');
        $this->addSql('ALTER TABLE shift_preset ADD CONSTRAINT FK_A0877627B03A8386 FOREIGN KEY (created_by_id) REFERENCES api_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE shift_preset_required_skill ADD CONSTRAINT FK_F091FC6F51FA5D06 FOREIGN KEY (shift_preset_id) REFERENCES shift_preset (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE shift_preset_required_skill ADD CONSTRAINT FK_F091FC6F5585C142 FOREIGN KEY (skill_id) REFERENCES skill (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shift_preset DROP CONSTRAINT FK_A0877627B03A8386');
        $this->addSql('ALTER TABLE shift_preset_required_skill DROP CONSTRAINT FK_F091FC6F51FA5D06');
        $this->addSql('ALTER TABLE shift_preset_required_skill DROP CONSTRAINT FK_F091FC6F5585C142');
        $this->addSql('DROP TABLE shift_preset_required_skill');
        $this->addSql('DROP TABLE shift_preset');
    }
}
