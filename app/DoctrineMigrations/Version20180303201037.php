<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180303201037 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE tag (id SERIAL NOT NULL, name VARCHAR(64) NOT NULL, slug VARCHAR(128) NOT NULL, color VARCHAR(7) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_389B783989D9B62 ON tag (slug)');
        $this->addSql('CREATE TABLE tagging (id SERIAL NOT NULL, tag_id INT NOT NULL, resource_class VARCHAR(255) NOT NULL, resource_id INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_A4AED123BAD26311 ON tagging (tag_id)');
        $this->addSql('CREATE UNIQUE INDEX tagging_unique ON tagging (resource_class, resource_id, tag_id)');
        $this->addSql('ALTER TABLE tagging ADD CONSTRAINT FK_A4AED123BAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');


        $this->addSql('ALTER TABLE tagging DROP CONSTRAINT FK_A4AED123BAD26311');
        $this->addSql('DROP TABLE tag');
        $this->addSql('DROP TABLE tagging');
    }
}
