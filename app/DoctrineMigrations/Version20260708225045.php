<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260708225045 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create employee_profile table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE employee_profile (id SERIAL NOT NULL, user_id INT NOT NULL, contract_start_date DATE DEFAULT NULL, date_of_birth DATE DEFAULT NULL, address_street VARCHAR(255) DEFAULT NULL, address_postal_code VARCHAR(255) DEFAULT NULL, address_locality VARCHAR(255) DEFAULT NULL, address_country VARCHAR(255) DEFAULT NULL, salary_type VARCHAR(16) DEFAULT NULL, salary_amount NUMERIC(10, 2) DEFAULT NULL, weekly_contracted_hours NUMERIC(5, 2) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_11BFC00A76ED395 ON employee_profile (user_id)');
        $this->addSql('ALTER TABLE employee_profile ADD CONSTRAINT FK_11BFC00A76ED395 FOREIGN KEY (user_id) REFERENCES api_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE employee_profile DROP CONSTRAINT FK_11BFC00A76ED395');
        $this->addSql('DROP TABLE employee_profile');
    }
}
