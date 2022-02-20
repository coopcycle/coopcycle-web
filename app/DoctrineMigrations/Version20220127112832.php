<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220127112832 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE delivery_form_submission (id SERIAL NOT NULL, delivery_form_id INT DEFAULT NULL, data TEXT NOT NULL, price INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_6404364A55E824A3 ON delivery_form_submission (delivery_form_id)');
        $this->addSql('ALTER TABLE delivery_form_submission ADD CONSTRAINT FK_6404364A55E824A3 FOREIGN KEY (delivery_form_id) REFERENCES delivery_form (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE delivery_form_submission');
    }
}
