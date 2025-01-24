<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250124000743 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE sylius_export_command (id SERIAL NOT NULL, created_by_id INT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, request_id VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_C23D6D27B03A8386 ON sylius_export_command (created_by_id)');
        $this->addSql('CREATE TABLE sylius_order_export (export_command_id INT NOT NULL, order_id INT NOT NULL, PRIMARY KEY(export_command_id, order_id))');
        $this->addSql('CREATE INDEX IDX_45C50E80F80DDD50 ON sylius_order_export (export_command_id)');
        $this->addSql('CREATE INDEX IDX_45C50E808D9F6D38 ON sylius_order_export (order_id)');
        $this->addSql('ALTER TABLE sylius_export_command ADD CONSTRAINT FK_C23D6D27B03A8386 FOREIGN KEY (created_by_id) REFERENCES api_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_order_export ADD CONSTRAINT FK_45C50E80F80DDD50 FOREIGN KEY (export_command_id) REFERENCES sylius_export_command (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_order_export ADD CONSTRAINT FK_45C50E808D9F6D38 FOREIGN KEY (order_id) REFERENCES sylius_order (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE sylius_export_command DROP CONSTRAINT FK_C23D6D27B03A8386');
        $this->addSql('ALTER TABLE sylius_order_export DROP CONSTRAINT FK_45C50E80F80DDD50');
        $this->addSql('ALTER TABLE sylius_order_export DROP CONSTRAINT FK_45C50E808D9F6D38');
        $this->addSql('DROP TABLE sylius_export_command');
        $this->addSql('DROP TABLE sylius_order_export');
    }
}
