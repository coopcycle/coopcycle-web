<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171221191054 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE tracking_position ADD courier_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE tracking_position ADD CONSTRAINT FK_6E519EEAE3D8151C FOREIGN KEY (courier_id) REFERENCES api_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_6E519EEAE3D8151C ON tracking_position (courier_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE tracking_position DROP CONSTRAINT FK_6E519EEAE3D8151C');
        $this->addSql('DROP INDEX IDX_6E519EEAE3D8151C');
        $this->addSql('ALTER TABLE tracking_position DROP courier_id');
    }
}
