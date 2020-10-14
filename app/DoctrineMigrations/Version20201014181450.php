<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201014181450 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE hub_closing_rule (hub_id INT NOT NULL, rule_id INT NOT NULL, PRIMARY KEY(hub_id, rule_id))');
        $this->addSql('CREATE INDEX IDX_E0B816906C786081 ON hub_closing_rule (hub_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E0B81690744E0351 ON hub_closing_rule (rule_id)');
        $this->addSql('ALTER TABLE hub_closing_rule ADD CONSTRAINT FK_E0B816906C786081 FOREIGN KEY (hub_id) REFERENCES hub (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE hub_closing_rule ADD CONSTRAINT FK_E0B81690744E0351 FOREIGN KEY (rule_id) REFERENCES closing_rule (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE restaurant_closing_rule (restaurant_id INT NOT NULL, rule_id INT NOT NULL, PRIMARY KEY(restaurant_id, rule_id))');
        $this->addSql('CREATE INDEX IDX_B625C6C5B1E7706E ON restaurant_closing_rule (restaurant_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B625C6C5744E0351 ON restaurant_closing_rule (rule_id)');
        $this->addSql('ALTER TABLE restaurant_closing_rule ADD CONSTRAINT FK_B625C6C5B1E7706E FOREIGN KEY (restaurant_id) REFERENCES restaurant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE restaurant_closing_rule ADD CONSTRAINT FK_B625C6C5744E0351 FOREIGN KEY (rule_id) REFERENCES closing_rule (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('INSERT INTO restaurant_closing_rule (restaurant_id, rule_id) SELECT restaurant_id, id FROM closing_rule');

        $this->addSql('ALTER TABLE closing_rule DROP CONSTRAINT fk_4b164485b1e7706e');
        $this->addSql('DROP INDEX idx_4b164485b1e7706e');
        $this->addSql('ALTER TABLE closing_rule DROP restaurant_id');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE closing_rule ADD restaurant_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE closing_rule ADD CONSTRAINT fk_4b164485b1e7706e FOREIGN KEY (restaurant_id) REFERENCES restaurant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_4b164485b1e7706e ON closing_rule (restaurant_id)');

        $this->addSql('UPDATE closing_rule cr SET restaurant_id = rcr.restaurant_id FROM restaurant_closing_rule rcr WHERE rcr.rule_id = cr.id');

        $this->addSql('DROP TABLE restaurant_closing_rule');
        $this->addSql('DROP TABLE hub_closing_rule');
    }
}
