<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The Zelty webhook secret is the same for the whole instance, and now comes from
 * the ZELTY_WEBHOOK_SECRET environment variable instead of being stored per restaurant.
 */
final class Version20260821091322 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop restaurant.zelty_webhook_secret_key';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE restaurant DROP zelty_webhook_secret_key');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE restaurant ADD zelty_webhook_secret_key VARCHAR(255) DEFAULT NULL');
    }
}
