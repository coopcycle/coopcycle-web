<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds pricing matrices, and the link from a pricing rule to the cell it was generated from.
 */
final class Version20261006071758 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add pricing_matrix, and matrix columns on pricing_rule';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE pricing_matrix (id SERIAL NOT NULL, rule_set_id INT NOT NULL, name VARCHAR(255) DEFAULT NULL, target VARCHAR(255) NOT NULL, task_type VARCHAR(255) DEFAULT NULL, row_axis JSON NOT NULL, column_axis JSON NOT NULL, cells JSON NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_62A000F18B51FD88 ON pricing_matrix (rule_set_id)');
        $this->addSql('ALTER TABLE pricing_matrix ADD CONSTRAINT FK_62A000F18B51FD88 FOREIGN KEY (rule_set_id) REFERENCES pricing_rule_set (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE pricing_rule ADD matrix_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE pricing_rule ADD matrix_row_key VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE pricing_rule ADD matrix_column_key VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE pricing_rule ADD CONSTRAINT FK_6DCEA672AA000BE7 FOREIGN KEY (matrix_id) REFERENCES pricing_matrix (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_6DCEA672AA000BE7 ON pricing_rule (matrix_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pricing_rule DROP CONSTRAINT FK_6DCEA672AA000BE7');
        $this->addSql('DROP INDEX IDX_6DCEA672AA000BE7');
        $this->addSql('ALTER TABLE pricing_rule DROP matrix_id');
        $this->addSql('ALTER TABLE pricing_rule DROP matrix_row_key');
        $this->addSql('ALTER TABLE pricing_rule DROP matrix_column_key');

        $this->addSql('ALTER TABLE pricing_matrix DROP CONSTRAINT FK_62A000F18B51FD88');
        $this->addSql('DROP TABLE pricing_matrix');
    }
}
