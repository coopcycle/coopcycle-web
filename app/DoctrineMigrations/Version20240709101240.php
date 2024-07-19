<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240709101240 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE task_list ADD vehicle_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE task_list ADD trailer_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE task_list ADD CONSTRAINT FK_377B6C63545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicle (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE task_list ADD CONSTRAINT FK_377B6C63B6C04CFD FOREIGN KEY (trailer_id) REFERENCES trailer (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_377B6C63545317D1 ON task_list (vehicle_id)');
        $this->addSql('CREATE INDEX IDX_377B6C63B6C04CFD ON task_list (trailer_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE task_list DROP CONSTRAINT FK_377B6C63545317D1');
        $this->addSql('ALTER TABLE task_list DROP CONSTRAINT FK_377B6C63B6C04CFD');
        $this->addSql('DROP INDEX IDX_377B6C63545317D1');
        $this->addSql('DROP INDEX IDX_377B6C63B6C04CFD');
        $this->addSql('ALTER TABLE task_list DROP vehicle_id');
        $this->addSql('ALTER TABLE task_list DROP trailer_id');
    }
}
