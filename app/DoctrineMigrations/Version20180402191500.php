<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180402191500 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE delivery DROP CONSTRAINT fk_3781ec109df894ed');
        $this->addSql('DROP INDEX idx_3781ec109df894ed');
        $this->addSql('ALTER TABLE delivery DROP tax_category_id');
        $this->addSql('ALTER TABLE delivery DROP total_excluding_tax');
        $this->addSql('ALTER TABLE delivery DROP total_tax');
        $this->addSql('ALTER TABLE delivery DROP total_including_tax');
        $this->addSql('ALTER TABLE delivery DROP date');
        $this->addSql('ALTER TABLE delivery DROP price');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');
    }
}
