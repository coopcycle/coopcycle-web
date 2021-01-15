<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180314160032 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE delivery_order (order_id INT NOT NULL, user_id INT NOT NULL, PRIMARY KEY(order_id, user_id))');
        $this->addSql('CREATE INDEX IDX_E522750A8D9F6D38 ON delivery_order (order_id)');
        $this->addSql('CREATE INDEX IDX_E522750AA76ED395 ON delivery_order (user_id)');
        $this->addSql('CREATE TABLE delivery_order_item (order_item_id INT NOT NULL, delivery_id INT NOT NULL, PRIMARY KEY(order_item_id, delivery_id))');
        $this->addSql('CREATE INDEX IDX_4CAF882DE415FB15 ON delivery_order_item (order_item_id)');
        $this->addSql('CREATE INDEX IDX_4CAF882D12136921 ON delivery_order_item (delivery_id)');
        $this->addSql('ALTER TABLE delivery_order ADD CONSTRAINT FK_E522750A8D9F6D38 FOREIGN KEY (order_id) REFERENCES sylius_order (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE delivery_order ADD CONSTRAINT FK_E522750AA76ED395 FOREIGN KEY (user_id) REFERENCES api_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE delivery_order_item ADD CONSTRAINT FK_4CAF882DE415FB15 FOREIGN KEY (order_item_id) REFERENCES sylius_order_item (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE delivery_order_item ADD CONSTRAINT FK_4CAF882D12136921 FOREIGN KEY (delivery_id) REFERENCES delivery (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE delivery_order');
        $this->addSql('DROP TABLE delivery_order_item');
    }
}
