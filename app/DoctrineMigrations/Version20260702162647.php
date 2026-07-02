<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260702162647 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add shift, shift_assignment & holiday_request tables for shift planning';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE holiday_request (id SERIAL NOT NULL, user_id INT NOT NULL, actioned_by_id INT DEFAULT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, status VARCHAR(16) NOT NULL, comment TEXT DEFAULT NULL, actioned_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_94ACA91A76ED395 ON holiday_request (user_id)');
        $this->addSql('CREATE INDEX IDX_94ACA91BD271DE7 ON holiday_request (actioned_by_id)');
        $this->addSql('CREATE INDEX IDX_94ACA9195275AB8845CBB3E ON holiday_request (start_date, end_date)');
        $this->addSql('CREATE INDEX IDX_94ACA917B00651C ON holiday_request (status)');
        $this->addSql('CREATE TABLE shift (id SERIAL NOT NULL, created_by_id INT DEFAULT NULL, type VARCHAR(32) NOT NULL, starts_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, ends_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, slots INT DEFAULT 1 NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_A50B3B45B03A8386 ON shift (created_by_id)');
        $this->addSql('CREATE INDEX IDX_A50B3B4555A0507C ON shift (starts_at)');
        $this->addSql('CREATE TABLE shift_assignment (id SERIAL NOT NULL, shift_id INT NOT NULL, user_id INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_47A0F95BB70BC0E ON shift_assignment (shift_id)');
        $this->addSql('CREATE INDEX IDX_47A0F95A76ED395 ON shift_assignment (user_id)');
        $this->addSql('CREATE UNIQUE INDEX shift_assignment_unique ON shift_assignment (shift_id, user_id)');
        $this->addSql('ALTER TABLE holiday_request ADD CONSTRAINT FK_94ACA91A76ED395 FOREIGN KEY (user_id) REFERENCES api_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE holiday_request ADD CONSTRAINT FK_94ACA91BD271DE7 FOREIGN KEY (actioned_by_id) REFERENCES api_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE shift ADD CONSTRAINT FK_A50B3B45B03A8386 FOREIGN KEY (created_by_id) REFERENCES api_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE shift_assignment ADD CONSTRAINT FK_47A0F95BB70BC0E FOREIGN KEY (shift_id) REFERENCES shift (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE shift_assignment ADD CONSTRAINT FK_47A0F95A76ED395 FOREIGN KEY (user_id) REFERENCES api_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE holiday_request DROP CONSTRAINT FK_94ACA91A76ED395');
        $this->addSql('ALTER TABLE holiday_request DROP CONSTRAINT FK_94ACA91BD271DE7');
        $this->addSql('ALTER TABLE shift DROP CONSTRAINT FK_A50B3B45B03A8386');
        $this->addSql('ALTER TABLE shift_assignment DROP CONSTRAINT FK_47A0F95BB70BC0E');
        $this->addSql('ALTER TABLE shift_assignment DROP CONSTRAINT FK_47A0F95A76ED395');
        $this->addSql('DROP TABLE holiday_request');
        $this->addSql('DROP TABLE shift');
        $this->addSql('DROP TABLE shift_assignment');
    }
}
