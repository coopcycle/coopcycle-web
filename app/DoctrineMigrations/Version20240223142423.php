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
        $this->addSql('ALTER TABLE restaurant ADD edenred_enabled BOOLEAN DEFAULT \'false\'');
        $this->addSql('ALTER TABLE restaurant ADD edenred_sync_sent BOOLEAN DEFAULT \'false\'');
        $this->addSql('ALTER TABLE restaurant ADD edenred_trcard_enabled BOOLEAN DEFAULT \'false\'');

        $this->addSql('UPDATE restaurant SET edenred_enabled = \'false\', edenred_sync_sent = \'false\', edenred_trcard_enabled = \'false\'');

        $this->addSql('ALTER TABLE restaurant ALTER edenred_enabled SET NOT NULL');
        $this->addSql('ALTER TABLE restaurant ALTER edenred_sync_sent SET NOT NULL');
        $this->addSql('ALTER TABLE restaurant ALTER edenred_trcard_enabled SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE restaurant DROP edenred_enabled');
        $this->addSql('ALTER TABLE restaurant DROP edenred_sync_sent');
        $this->addSql('ALTER TABLE restaurant DROP edenred_trcard_enabled');
    }
}
