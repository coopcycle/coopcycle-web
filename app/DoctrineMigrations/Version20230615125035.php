<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230615125035 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sylius_order_loopeat_details (id SERIAL NOT NULL, order_id INT DEFAULT NULL, loopeat_order_id VARCHAR(255) DEFAULT NULL, returns JSON DEFAULT NULL, deliver JSON DEFAULT NULL, pickup JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_44D991228D9F6D38 ON sylius_order_loopeat_details (order_id)');
        $this->addSql('COMMENT ON COLUMN sylius_order_loopeat_details.returns IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN sylius_order_loopeat_details.deliver IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN sylius_order_loopeat_details.pickup IS \'(DC2Type:json_array)\'');
        $this->addSql('ALTER TABLE sylius_order_loopeat_details ADD CONSTRAINT FK_44D991228D9F6D38 FOREIGN KEY (order_id) REFERENCES sylius_order (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE sylius_order_loopeat_details');
    }
}
