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

        if (!$channel = $stmt->fetch()) {
            $this->addSql('INSERT INTO sylius_channel (code, name, enabled, created_at, updated_at) VALUES (:code, :name, \'t\', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)', [
                'code' => 'web',
                'name' => 'Web'
            ]);
        }

        $this->addSql('UPDATE sylius_order SET channel_id = (SELECT id FROM sylius_channel WHERE code = \'web\')');
    }

    public function down(Schema $schema) : void
    {
    }
}
