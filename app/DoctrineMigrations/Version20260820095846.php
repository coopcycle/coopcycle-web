<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Stores Zelty API traffic (requests sent to Zelty and webhooks received from it).
 */
final class Version20260820095846 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add zelty_api_log';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE zelty_api_log (id SERIAL NOT NULL, restaurant_id INT DEFAULT NULL, direction VARCHAR(16) NOT NULL, method VARCHAR(10) DEFAULT NULL, endpoint TEXT DEFAULT NULL, status_code INT DEFAULT NULL, request_body TEXT DEFAULT NULL, response_body TEXT DEFAULT NULL, duration_ms INT DEFAULT NULL, level VARCHAR(16) DEFAULT NULL, message TEXT DEFAULT NULL, error TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_1B95034EB1E7706E ON zelty_api_log (restaurant_id)');
        $this->addSql('CREATE INDEX idx_zelty_api_log_restaurant_created ON zelty_api_log (restaurant_id, created_at)');
        $this->addSql('CREATE INDEX idx_zelty_api_log_created ON zelty_api_log (created_at)');
        $this->addSql('ALTER TABLE zelty_api_log ADD CONSTRAINT FK_1B95034EB1E7706E FOREIGN KEY (restaurant_id) REFERENCES restaurant (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE zelty_api_log DROP CONSTRAINT FK_1B95034EB1E7706E');
        $this->addSql('DROP TABLE zelty_api_log');
    }
}
