<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190131125549 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE api_user ADD channel_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE api_user ADD CONSTRAINT FK_AC64A0BA72F5A1AA FOREIGN KEY (channel_id) REFERENCES sylius_channel (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_AC64A0BA72F5A1AA ON api_user (channel_id)');
        $this->addSql('UPDATE api_user SET channel_id = (SELECT id FROM sylius_channel WHERE code = \'web\') WHERE channel_id IS NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE api_user DROP CONSTRAINT FK_AC64A0BA72F5A1AA');
        $this->addSql('DROP INDEX IDX_AC64A0BA72F5A1AA');
        $this->addSql('ALTER TABLE api_user DROP channel_id');
    }
}
