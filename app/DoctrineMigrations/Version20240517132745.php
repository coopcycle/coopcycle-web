<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240517132745 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE store ADD transporter VARCHAR(255) DEFAULT \'NULL\'');
        $this->addSql('UPDATE store SET transporter = \'DBSCHENKER\' WHERE db_schenker = true');
        $this->addSql('ALTER TABLE store DROP db_schenker');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE store ADD db_schenker BOOLEAN DEFAULT \'false\' NOT NULL');
        $this->addSql('UPDATE store SET db_schenker = true WHERE transporter = \'DBSCHENKER\'');
        $this->addSql('ALTER TABLE store DROP transporter');
    }
}
