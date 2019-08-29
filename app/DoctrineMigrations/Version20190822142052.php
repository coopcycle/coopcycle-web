<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190822142052 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE invitation (code VARCHAR(6) NOT NULL, email VARCHAR(256) NOT NULL, sent BOOLEAN NOT NULL, PRIMARY KEY(code))');

        $this->addSql('ALTER TABLE api_user ADD invitation_code VARCHAR(6) DEFAULT NULL');
        $this->addSql('ALTER TABLE api_user ADD CONSTRAINT FK_AC64A0BABA14FCCC FOREIGN KEY (invitation_code) REFERENCES invitation (code) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_AC64A0BABA14FCCC ON api_user (invitation_code)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');


        $this->addSql('DROP TABLE invitation');
        $this->addSql('DROP INDEX UNIQ_AC64A0BABA14FCCC');
        $this->addSql('ALTER TABLE api_user DROP invitation_code');
    }
}
