<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190128215939 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE sylius_order ADD channel_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_order ADD CONSTRAINT FK_6196A1F972F5A1AA FOREIGN KEY (channel_id) REFERENCES sylius_channel (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_6196A1F972F5A1AA ON sylius_order (channel_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE sylius_order DROP CONSTRAINT FK_6196A1F972F5A1AA');
        $this->addSql('DROP INDEX IDX_6196A1F972F5A1AA');
        $this->addSql('ALTER TABLE sylius_order DROP channel_id');
    }
}
