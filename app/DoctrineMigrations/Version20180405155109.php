<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180405155109 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE delivery DROP CONSTRAINT fk_3781ec108d9f6d38');
        $this->addSql('DROP INDEX uniq_3781ec108d9f6d38');
        $this->addSql('ALTER TABLE delivery DROP order_id');

        $this->addSql('ALTER TABLE delivery DROP CONSTRAINT fk_3781ec10722436d7');
        $this->addSql('DROP INDEX uniq_3781ec10722436d7');
        $this->addSql('ALTER TABLE delivery RENAME COLUMN sylius_order_id TO order_id');
        $this->addSql('ALTER TABLE delivery ADD CONSTRAINT FK_3781EC108D9F6D38 FOREIGN KEY (order_id) REFERENCES sylius_order (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3781EC108D9F6D38 ON delivery (order_id)');
    }

    public function down(Schema $schema) : void
    {
    }
}
