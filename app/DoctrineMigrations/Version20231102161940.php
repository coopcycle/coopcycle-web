<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231102161940 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE business_account ADD billing_address_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE business_account ADD CONSTRAINT FK_2005EDE979D0C0E4 FOREIGN KEY (billing_address_id) REFERENCES address (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2005EDE979D0C0E4 ON business_account (billing_address_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE business_account DROP CONSTRAINT FK_2005EDE979D0C0E4');
        $this->addSql('DROP INDEX UNIQ_2005EDE979D0C0E4');
        $this->addSql('ALTER TABLE business_account DROP billing_address_id');
    }
}
