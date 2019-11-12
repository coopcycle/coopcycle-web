<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180314141413 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE sylius_adjustment (id SERIAL NOT NULL, order_id INT DEFAULT NULL, order_item_id INT DEFAULT NULL, order_item_unit_id INT DEFAULT NULL, type VARCHAR(255) NOT NULL, label VARCHAR(255) DEFAULT NULL, amount INT NOT NULL, is_neutral BOOLEAN NOT NULL, is_locked BOOLEAN NOT NULL, origin_code VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_ACA6E0F28D9F6D38 ON sylius_adjustment (order_id)');
        $this->addSql('CREATE INDEX IDX_ACA6E0F2E415FB15 ON sylius_adjustment (order_item_id)');
        $this->addSql('CREATE INDEX IDX_ACA6E0F2F720C233 ON sylius_adjustment (order_item_unit_id)');
        $this->addSql('CREATE TABLE sylius_order (id SERIAL NOT NULL, number VARCHAR(255) DEFAULT NULL, notes TEXT DEFAULT NULL, state VARCHAR(255) NOT NULL, checkout_completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, items_total INT NOT NULL, adjustments_total INT NOT NULL, total INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6196A1F996901F54 ON sylius_order (number)');
        $this->addSql('CREATE TABLE sylius_order_item (id SERIAL NOT NULL, order_id INT NOT NULL, quantity INT NOT NULL, unit_price INT NOT NULL, units_total INT NOT NULL, adjustments_total INT NOT NULL, total INT NOT NULL, is_immutable BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_77B587ED8D9F6D38 ON sylius_order_item (order_id)');
        $this->addSql('CREATE TABLE sylius_order_item_unit (id SERIAL NOT NULL, order_item_id INT NOT NULL, adjustments_total INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_82BF226EE415FB15 ON sylius_order_item_unit (order_item_id)');
        $this->addSql('CREATE TABLE sylius_order_sequence (id SERIAL NOT NULL, idx INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE sylius_adjustment ADD CONSTRAINT FK_ACA6E0F28D9F6D38 FOREIGN KEY (order_id) REFERENCES sylius_order (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_adjustment ADD CONSTRAINT FK_ACA6E0F2E415FB15 FOREIGN KEY (order_item_id) REFERENCES sylius_order_item (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_adjustment ADD CONSTRAINT FK_ACA6E0F2F720C233 FOREIGN KEY (order_item_unit_id) REFERENCES sylius_order_item_unit (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_order_item ADD CONSTRAINT FK_77B587ED8D9F6D38 FOREIGN KEY (order_id) REFERENCES sylius_order (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_order_item_unit ADD CONSTRAINT FK_82BF226EE415FB15 FOREIGN KEY (order_item_id) REFERENCES sylius_order_item (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE sylius_adjustment DROP CONSTRAINT FK_ACA6E0F28D9F6D38');
        $this->addSql('ALTER TABLE sylius_order_item DROP CONSTRAINT FK_77B587ED8D9F6D38');
        $this->addSql('ALTER TABLE sylius_adjustment DROP CONSTRAINT FK_ACA6E0F2E415FB15');
        $this->addSql('ALTER TABLE sylius_order_item_unit DROP CONSTRAINT FK_82BF226EE415FB15');
        $this->addSql('ALTER TABLE sylius_adjustment DROP CONSTRAINT FK_ACA6E0F2F720C233');
        $this->addSql('DROP TABLE sylius_adjustment');
        $this->addSql('DROP TABLE sylius_order');
        $this->addSql('DROP TABLE sylius_order_item');
        $this->addSql('DROP TABLE sylius_order_item_unit');
        $this->addSql('DROP TABLE sylius_order_sequence');
    }
}
