<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190818113954 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE reusable_packaging_unit (id SERIAL NOT NULL, stockable_id INT DEFAULT NULL, user_id INT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_21479CEFFBE8234 ON reusable_packaging_unit (stockable_id)');
        $this->addSql('CREATE INDEX IDX_21479CEFA76ED395 ON reusable_packaging_unit (user_id)');
        $this->addSql('ALTER TABLE reusable_packaging_unit ADD CONSTRAINT FK_21479CEFFBE8234 FOREIGN KEY (stockable_id) REFERENCES reusable_packaging (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE reusable_packaging_unit ADD CONSTRAINT FK_21479CEFA76ED395 FOREIGN KEY (user_id) REFERENCES api_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE reusable_packaging_unit');
    }
}
