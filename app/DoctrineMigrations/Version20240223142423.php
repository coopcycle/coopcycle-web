<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240223142423 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE restaurant ADD edenred_merchant_active BOOLEAN DEFAULT \'false\' NOT NULL');
        $this->addSql('ALTER TABLE restaurant ADD edenred_sync_sent BOOLEAN DEFAULT \'false\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE restaurant DROP edenred_merchant_active');
        $this->addSql('ALTER TABLE restaurant DROP edenred_sync_sent');
    }
}
