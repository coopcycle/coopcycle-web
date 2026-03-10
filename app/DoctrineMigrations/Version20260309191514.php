<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260309191514 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Farewell Dabba';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE sylius_customer_dabba_credentials_id_seq CASCADE');
        $this->addSql('ALTER TABLE sylius_customer_dabba_credentials DROP CONSTRAINT fk_6a1688cf9395c3f3');
        $this->addSql('DROP TABLE sylius_customer_dabba_credentials');
        $this->addSql('ALTER TABLE restaurant DROP dabba_enabled');
        $this->addSql('ALTER TABLE restaurant DROP dabba_code');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
