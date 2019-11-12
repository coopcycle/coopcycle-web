<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180628182441 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE delivery DROP CONSTRAINT fk_3781ec10ebf23851');
        $this->addSql('ALTER TABLE delivery DROP CONSTRAINT fk_3781ec104c6cf538');
        $this->addSql('DROP INDEX idx_3781ec10ebf23851');
        $this->addSql('DROP INDEX idx_3781ec104c6cf538');
        $this->addSql('ALTER TABLE delivery DROP origin_address_id');
        $this->addSql('ALTER TABLE delivery DROP delivery_address_id');
    }

    public function down(Schema $schema) : void
    {
    }
}
