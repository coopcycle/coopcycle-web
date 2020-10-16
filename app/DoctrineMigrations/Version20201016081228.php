<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201016081228 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE restaurant ADD contract_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE restaurant ADD CONSTRAINT FK_EB95123F2576E0FD FOREIGN KEY (contract_id) REFERENCES contract (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_EB95123F2576E0FD ON restaurant (contract_id)');

        $this->addSql('UPDATE restaurant r SET contract_id = c.id FROM contract c WHERE r.id = c.restaurant_id');

        $this->addSql('ALTER TABLE contract DROP CONSTRAINT fk_e98f2859b1e7706e');
        $this->addSql('DROP INDEX uniq_e98f2859b1e7706e');
        $this->addSql('ALTER TABLE contract DROP restaurant_id');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE contract ADD restaurant_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE contract ADD CONSTRAINT fk_e98f2859b1e7706e FOREIGN KEY (restaurant_id) REFERENCES restaurant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX uniq_e98f2859b1e7706e ON contract (restaurant_id)');

        $this->addSql('UPDATE contract c SET restaurant_id = r.id FROM restaurant r WHERE c.id = r.contract_id');

        $this->addSql('ALTER TABLE restaurant DROP CONSTRAINT FK_EB95123F2576E0FD');
        $this->addSql('DROP INDEX IDX_EB95123F2576E0FD');
        $this->addSql('ALTER TABLE restaurant DROP contract_id');
    }
}
