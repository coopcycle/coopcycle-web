<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240520115034 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE task_list ALTER distance SET NOT NULL');
        $this->addSql('ALTER TABLE task_list ALTER duration SET NOT NULL');
        $this->addSql('ALTER TABLE task_list ALTER polyline SET NOT NULL');
        $this->addSql('ALTER TABLE task_list ALTER created_at SET NOT NULL');
        $this->addSql('ALTER TABLE task_list ALTER updated_at SET NOT NULL');

    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
