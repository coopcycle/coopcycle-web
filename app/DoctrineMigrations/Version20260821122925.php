<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260821122925 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add availability_rule (recurring weekly employee availability/unavailability)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE availability_rule (
              id SERIAL NOT NULL,
              user_id INT NOT NULL,
              type VARCHAR(16) NOT NULL,
              day_of_week SMALLINT NOT NULL,
              start_time TIME(0) WITHOUT TIME ZONE NOT NULL,
              end_time TIME(0) WITHOUT TIME ZONE NOT NULL,
              comment TEXT DEFAULT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_F6C75035A76ED3956A79171 ON availability_rule (user_id, day_of_week)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              availability_rule
            ADD
              CONSTRAINT FK_F6C75035A76ED395 FOREIGN KEY (user_id) REFERENCES api_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE availability_rule DROP CONSTRAINT FK_F6C75035A76ED395');
        $this->addSql('DROP TABLE availability_rule');
    }
}
