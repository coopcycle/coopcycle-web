<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240712004722 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE sylius_order_bookmark (id SERIAL NOT NULL, order_id INT NOT NULL, owner_id INT NOT NULL, role VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_E7A0DA578D9F6D38 ON sylius_order_bookmark (order_id)');
        $this->addSql('CREATE INDEX IDX_E7A0DA577E3C61F9 ON sylius_order_bookmark (owner_id)');
        $this->addSql('ALTER TABLE sylius_order_bookmark ADD CONSTRAINT FK_E7A0DA578D9F6D38 FOREIGN KEY (order_id) REFERENCES sylius_order (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_order_bookmark ADD CONSTRAINT FK_E7A0DA577E3C61F9 FOREIGN KEY (owner_id) REFERENCES api_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE sylius_order_bookmark DROP CONSTRAINT FK_E7A0DA578D9F6D38');
        $this->addSql('ALTER TABLE sylius_order_bookmark DROP CONSTRAINT FK_E7A0DA577E3C61F9');
        $this->addSql('DROP TABLE sylius_order_bookmark');
    }
}
