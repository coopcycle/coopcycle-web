<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240709071543 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE trailer ALTER color TYPE VARCHAR(7)');
        $this->addSql('ALTER TABLE vehicle ALTER color TYPE VARCHAR(7)');
        $this->addSql('ALTER TABLE warehouse ADD deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE trailer ALTER color TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE warehouse DROP deleted_at');
        $this->addSql('ALTER TABLE vehicle ALTER color TYPE VARCHAR(255)');
    }
}
