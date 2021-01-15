<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180405215415 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE order_item_modifier DROP CONSTRAINT fk_6ad159e4e415fb15');
        $this->addSql('ALTER TABLE order_event DROP CONSTRAINT fk_b8307e5a8d9f6d38');
        $this->addSql('ALTER TABLE order_item DROP CONSTRAINT fk_52ea1f098d9f6d38');

        $this->addSql('DROP SEQUENCE order__id_seq CASCADE');
        $this->addSql('DROP SEQUENCE order_event_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE order_item_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE order_item_modifier_id_seq CASCADE');

        $this->addSql('DROP TABLE order_event');
        $this->addSql('DROP TABLE order_item');
        $this->addSql('DROP TABLE order_item_modifier');
        $this->addSql('DROP TABLE order_');
    }

    public function down(Schema $schema) : void
    {
    }
}
