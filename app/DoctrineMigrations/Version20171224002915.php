<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171224002915 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE pricing_rule_set (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE pricing_rule ADD rule_set_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE pricing_rule ADD CONSTRAINT FK_6DCEA6728B51FD88 FOREIGN KEY (rule_set_id) REFERENCES pricing_rule_set (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_6DCEA6728B51FD88 ON pricing_rule (rule_set_id)');

        $this->addSql('INSERT INTO pricing_rule_set (name) VALUES (:name)', ['name' => 'Default']);
        $this->addSql('UPDATE pricing_rule SET rule_set_id = (SELECT id FROM pricing_rule_set WHERE name = :name)', ['name' => 'Default']);

        $this->addSql('ALTER TABLE pricing_rule ALTER rule_set_id SET NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE pricing_rule DROP CONSTRAINT FK_6DCEA6728B51FD88');
        $this->addSql('DROP TABLE pricing_rule_set');
        $this->addSql('DROP INDEX IDX_6DCEA6728B51FD88');
        $this->addSql('ALTER TABLE pricing_rule DROP rule_set_id');
    }
}
