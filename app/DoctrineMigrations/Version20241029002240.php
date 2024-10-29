<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241029002240 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE task_rrule ADD generate_orders BOOLEAN DEFAULT \'false\' NOT NULL');

        $this->addSql('UPDATE task_rrule SET generate_orders = true WHERE id IN (SELECT rr.id FROM task_rrule rr JOIN sylius_order o ON o.subscription_id = rr.id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE task_rrule DROP generate_orders');
    }
}
