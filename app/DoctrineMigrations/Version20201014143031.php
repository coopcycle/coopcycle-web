<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201014143031 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Create sylius_order_target table';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE sylius_order_target (id SERIAL NOT NULL, restaurant_id INT DEFAULT NULL, hub_id INT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_412637E8B1E7706E ON sylius_order_target (restaurant_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_412637E86C786081 ON sylius_order_target (hub_id)');
        $this->addSql('ALTER TABLE sylius_order_target ADD CONSTRAINT FK_412637E8B1E7706E FOREIGN KEY (restaurant_id) REFERENCES restaurant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_order_target ADD CONSTRAINT FK_412637E86C786081 FOREIGN KEY (hub_id) REFERENCES hub (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE sylius_order_target');
    }
}
