<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The Zelty API log now records what each call actually changed on our side,
 * which is what the "Activity" view renders in plain language.
 */
final class Version20260825101500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add zelty_api_log.events';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE zelty_api_log ADD events JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE zelty_api_log DROP events');
    }
}
