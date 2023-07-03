<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230629195929 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sylius_order_loopeat_credentials (id SERIAL NOT NULL, order_id INT DEFAULT NULL, loopeat_access_token TEXT DEFAULT NULL, loopeat_refresh_token TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_DEC04C298D9F6D38 ON sylius_order_loopeat_credentials (order_id)');
        $this->addSql('ALTER TABLE sylius_order_loopeat_credentials ADD CONSTRAINT FK_DEC04C298D9F6D38 FOREIGN KEY (order_id) REFERENCES sylius_order (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE sylius_order_loopeat_credentials');
    }
}
