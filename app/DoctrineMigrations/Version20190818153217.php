<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190818153217 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE reusable_packaging_unit ADD operation VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE reusable_packaging_unit ADD value INT NOT NULL');
        $this->addSql('ALTER TABLE reusable_packaging_unit ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE reusable_packaging_unit DROP operation');
        $this->addSql('ALTER TABLE reusable_packaging_unit DROP value');
        $this->addSql('ALTER TABLE reusable_packaging_unit DROP created_at');
    }
}
