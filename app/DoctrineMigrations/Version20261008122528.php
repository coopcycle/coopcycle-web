<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds the manual supplements of the orders generated from a recurrence rule.
 */
final class Version20261008122528 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add task_rrule_manual_supplement';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE task_rrule_manual_supplement (id SERIAL NOT NULL, recurrence_rule_id INT NOT NULL, pricing_rule_id INT NOT NULL, quantity INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_8DDDEA322344888A ON task_rrule_manual_supplement (recurrence_rule_id)');
        $this->addSql('CREATE INDEX IDX_8DDDEA32B5B58DBB ON task_rrule_manual_supplement (pricing_rule_id)');
        $this->addSql('ALTER TABLE task_rrule_manual_supplement ADD CONSTRAINT FK_8DDDEA322344888A FOREIGN KEY (recurrence_rule_id) REFERENCES task_rrule (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE task_rrule_manual_supplement ADD CONSTRAINT FK_8DDDEA32B5B58DBB FOREIGN KEY (pricing_rule_id) REFERENCES pricing_rule (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task_rrule_manual_supplement DROP CONSTRAINT FK_8DDDEA322344888A');
        $this->addSql('ALTER TABLE task_rrule_manual_supplement DROP CONSTRAINT FK_8DDDEA32B5B58DBB');
        $this->addSql('DROP TABLE task_rrule_manual_supplement');
    }
}
