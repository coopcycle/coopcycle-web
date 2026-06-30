<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260415135026 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE restaurant_day_of_week_delivery_perimeter_expression (id SERIAL NOT NULL, restaurant_id INT DEFAULT NULL, expression VARCHAR(255) NOT NULL, days_of_week VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_9E158BE3B1E7706E ON restaurant_day_of_week_delivery_perimeter_expression (restaurant_id)');
        $this->addSql('ALTER TABLE restaurant_day_of_week_delivery_perimeter_expression ADD CONSTRAINT FK_9E158BE3B1E7706E FOREIGN KEY (restaurant_id) REFERENCES restaurant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE restaurant_day_of_week_delivery_perimeter_expression DROP CONSTRAINT FK_9E158BE3B1E7706E');
        $this->addSql('DROP TABLE restaurant_day_of_week_delivery_perimeter_expression');
    }
}
