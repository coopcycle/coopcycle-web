<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220707163442 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE task t SET status = \'CANCELLED\' FROM delivery d JOIN sylius_order o ON d.order_id = o.id WHERE t.delivery_id = d.id AND o.state = \'refused\' AND t.status = \'TODO\'');

    }

    public function down(Schema $schema): void
    {
    }
}
