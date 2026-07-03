<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260212092155 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE shop_collection (id SERIAL NOT NULL, title TEXT NOT NULL, subtitle TEXT DEFAULT NULL, slug VARCHAR(156) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_863E8814989D9B62 ON shop_collection (slug)');
        $this->addSql('CREATE TABLE shop_collection_item (id SERIAL NOT NULL, shop_id INT DEFAULT NULL, collection_id INT DEFAULT NULL, position INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_BC9F0DC44D16C4DD ON shop_collection_item (shop_id)');
        $this->addSql('CREATE INDEX IDX_BC9F0DC4514956FD ON shop_collection_item (collection_id)');
        $this->addSql('ALTER TABLE shop_collection_item ADD CONSTRAINT FK_BC9F0DC44D16C4DD FOREIGN KEY (shop_id) REFERENCES restaurant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE shop_collection_item ADD CONSTRAINT FK_BC9F0DC4514956FD FOREIGN KEY (collection_id) REFERENCES shop_collection (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_collection_item DROP CONSTRAINT FK_BC9F0DC44D16C4DD');
        $this->addSql('ALTER TABLE shop_collection_item DROP CONSTRAINT FK_BC9F0DC4514956FD');
        $this->addSql('DROP TABLE shop_collection');
        $this->addSql('DROP TABLE shop_collection_item');
    }
}
