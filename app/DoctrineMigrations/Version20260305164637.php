<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260305164637 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE restaurant_day_of_week_address (id SERIAL NOT NULL, restaurant_id INT DEFAULT NULL, address_id INT DEFAULT NULL, days_of_week VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_5EE47A0AB1E7706E ON restaurant_day_of_week_address (restaurant_id)');
        $this->addSql('CREATE INDEX IDX_5EE47A0AF5B7AF75 ON restaurant_day_of_week_address (address_id)');
        $this->addSql('ALTER TABLE restaurant_day_of_week_address ADD CONSTRAINT FK_5EE47A0AB1E7706E FOREIGN KEY (restaurant_id) REFERENCES restaurant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE restaurant_day_of_week_address ADD CONSTRAINT FK_5EE47A0AF5B7AF75 FOREIGN KEY (address_id) REFERENCES address (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE restaurant_day_of_week_address DROP CONSTRAINT FK_5EE47A0AB1E7706E');
        $this->addSql('ALTER TABLE restaurant_day_of_week_address DROP CONSTRAINT FK_5EE47A0AF5B7AF75');
        $this->addSql('DROP TABLE restaurant_day_of_week_address');
    }
}
