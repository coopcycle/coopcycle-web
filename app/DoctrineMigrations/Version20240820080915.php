<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240820080915 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql("ALTER TABLE package ADD description VARCHAR(255) NOT NULL DEFAULT ''");
        $this->addSql('ALTER TABLE package RENAME COLUMN volume_units TO average_volume_units');

        $this->addSql('ALTER TABLE package ADD max_volume_units INT');
        $this->addSql('ALTER TABLE package ADD average_weight INT');
        $this->addSql('ALTER TABLE package ADD max_weight INT');
        $this->addSql('ALTER TABLE package ADD short_code VARCHAR(2)');

        // FIXME ? SUBSTRING(name, 0, 2) seems to return only the first caracter of name, it is weird...
        $this->addSql('UPDATE package SET max_volume_units = average_volume_units * 1 / 0.75, short_code = UPPER(SUBSTRING(name, 0, 3))');

        $this->addSql('ALTER TABLE package ALTER COLUMN max_volume_units SET NOT NULL');
        $this->addSql('ALTER TABLE package ALTER COLUMN short_code SET NOT NULL');

    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE package DROP description');
        $this->addSql('ALTER TABLE package DROP max_volume_units');
        $this->addSql('ALTER TABLE package RENAME COLUMN average_volume_units TO volume_units');
        $this->addSql('ALTER TABLE package DROP average_weight');
        $this->addSql('ALTER TABLE package DROP max_weight');
        $this->addSql('ALTER TABLE package DROP short_code');
    }
}
