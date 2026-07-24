<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260710153227 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add skill, skill_user and shift_required_skill tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE skill (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE skill_user (skill_id INT NOT NULL, user_id INT NOT NULL, PRIMARY KEY(skill_id, user_id))');
        $this->addSql('CREATE INDEX IDX_CAD24AFB5585C142 ON skill_user (skill_id)');
        $this->addSql('CREATE INDEX IDX_CAD24AFBA76ED395 ON skill_user (user_id)');
        $this->addSql('CREATE TABLE shift_required_skill (shift_id INT NOT NULL, skill_id INT NOT NULL, PRIMARY KEY(shift_id, skill_id))');
        $this->addSql('CREATE INDEX IDX_4E19644BBB70BC0E ON shift_required_skill (shift_id)');
        $this->addSql('CREATE INDEX IDX_4E19644B5585C142 ON shift_required_skill (skill_id)');
        $this->addSql('ALTER TABLE skill_user ADD CONSTRAINT FK_CAD24AFB5585C142 FOREIGN KEY (skill_id) REFERENCES skill (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE skill_user ADD CONSTRAINT FK_CAD24AFBA76ED395 FOREIGN KEY (user_id) REFERENCES api_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE shift_required_skill ADD CONSTRAINT FK_4E19644BBB70BC0E FOREIGN KEY (shift_id) REFERENCES shift (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE shift_required_skill ADD CONSTRAINT FK_4E19644B5585C142 FOREIGN KEY (skill_id) REFERENCES skill (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shift_required_skill DROP CONSTRAINT FK_4E19644BBB70BC0E');
        $this->addSql('ALTER TABLE shift_required_skill DROP CONSTRAINT FK_4E19644B5585C142');
        $this->addSql('ALTER TABLE skill_user DROP CONSTRAINT FK_CAD24AFB5585C142');
        $this->addSql('ALTER TABLE skill_user DROP CONSTRAINT FK_CAD24AFBA76ED395');
        $this->addSql('DROP TABLE shift_required_skill');
        $this->addSql('DROP TABLE skill_user');
        $this->addSql('DROP TABLE skill');
    }
}
