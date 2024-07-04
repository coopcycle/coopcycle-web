<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240521164311 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE sylius_order_event DROP CONSTRAINT FK_1F7207A3D0BBCCBE');
        $this->addSql('ALTER TABLE sylius_order_event ADD CONSTRAINT FK_1F7207A3D0BBCCBE FOREIGN KEY (aggregate_id) REFERENCES sylius_order (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE sylius_order_event DROP CONSTRAINT fk_1f7207a3d0bbccbe');
        $this->addSql('ALTER TABLE sylius_order_event ADD CONSTRAINT fk_1f7207a3d0bbccbe FOREIGN KEY (aggregate_id) REFERENCES sylius_order (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
