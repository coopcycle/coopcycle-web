<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20190128225322 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        $stmt = $this->connection->prepare('SELECT id FROM sylius_channel WHERE code = \'web\'');

        $stmt->execute();

        if ($channel = $stmt->fetch()) {
            $this->addSql('UPDATE sylius_order SET channel_id = :channel_id', [
                'channel_id' => $channel['id'],
            ]);
        }
    }

    public function down(Schema $schema) : void
    {
    }
}
