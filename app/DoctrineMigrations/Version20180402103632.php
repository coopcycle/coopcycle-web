<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180402103632 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE order_ ALTER total_excluding_tax DROP NOT NULL');
        $this->addSql('ALTER TABLE order_ ALTER total_tax DROP NOT NULL');
        $this->addSql('ALTER TABLE order_ ALTER total_including_tax DROP NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE order_ ALTER total_excluding_tax SET NOT NULL');
        $this->addSql('ALTER TABLE order_ ALTER total_tax SET NOT NULL');
        $this->addSql('ALTER TABLE order_ ALTER total_including_tax SET NOT NULL');
    }
}
