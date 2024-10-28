<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240927224218 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE business_account ADD legal_name VARCHAR(255) NOT NULL default \'\'');
        $this->addSql('ALTER TABLE business_account ADD vat_number VARCHAR(255) NOT NULL default \'\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE business_account DROP legal_name');
        $this->addSql('ALTER TABLE business_account DROP vat_number');
    }
}
