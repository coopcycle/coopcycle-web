<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240109151132 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_order DROP CONSTRAINT fk_6196a1f9f603ee73');
        $this->addSql('DROP SEQUENCE vendor_id_seq CASCADE');
        $this->addSql('DROP TABLE vendor');
        $this->addSql('DROP INDEX idx_6196a1f9f603ee73');
        $this->addSql('ALTER TABLE sylius_order DROP vendor_id');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
