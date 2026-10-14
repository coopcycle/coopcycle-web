<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds the pricing rule set chosen by a dispatcher on a recurrence rule.
 */
final class Version20261007142503 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add pricing_rule_set_id on task_rrule';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task_rrule ADD pricing_rule_set_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE task_rrule ADD CONSTRAINT FK_E5082C42C213A00E FOREIGN KEY (pricing_rule_set_id) REFERENCES pricing_rule_set (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_E5082C42C213A00E ON task_rrule (pricing_rule_set_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task_rrule DROP CONSTRAINT FK_E5082C42C213A00E');
        $this->addSql('DROP INDEX IDX_E5082C42C213A00E');
        $this->addSql('ALTER TABLE task_rrule DROP pricing_rule_set_id');
    }
}
