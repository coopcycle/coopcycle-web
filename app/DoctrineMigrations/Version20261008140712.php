<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008140712 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the loyalty program tables: the points ledger and the reward catalogue customers spend points on';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE loyalty_reward (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, position INT DEFAULT 0 NOT NULL, points_cost INT NOT NULL, reward_type VARCHAR(32) NOT NULL, reward_amount INT DEFAULT NULL, reward_percentage DOUBLE PRECISION DEFAULT NULL, coupon_validity_days INT DEFAULT 30 NOT NULL, enabled BOOLEAN DEFAULT true NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');

        $this->addSql('CREATE TABLE loyalty_points_entry (id SERIAL NOT NULL, customer_id INT NOT NULL, reward_id INT DEFAULT NULL, coupon_id INT DEFAULT NULL, order_id INT DEFAULT NULL, type VARCHAR(16) NOT NULL, amount INT NOT NULL, remaining INT DEFAULT 0 NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_DD2816B59395C3F3 ON loyalty_points_entry (customer_id)');
        $this->addSql('CREATE INDEX IDX_DD2816B5E466ACA1 ON loyalty_points_entry (reward_id)');
        $this->addSql('CREATE INDEX IDX_DD2816B566C5951B ON loyalty_points_entry (coupon_id)');
        $this->addSql('CREATE INDEX IDX_DD2816B58D9F6D38 ON loyalty_points_entry (order_id)');
        // Covers both the balance sum and the oldest-first spend walk.
        $this->addSql('CREATE INDEX idx_loyalty_points_entry_balance ON loyalty_points_entry (customer_id, type, expires_at)');

        $this->addSql('ALTER TABLE loyalty_points_entry ADD CONSTRAINT FK_DD2816B59395C3F3 FOREIGN KEY (customer_id) REFERENCES sylius_customer (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE loyalty_points_entry ADD CONSTRAINT FK_DD2816B5E466ACA1 FOREIGN KEY (reward_id) REFERENCES loyalty_reward (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE loyalty_points_entry ADD CONSTRAINT FK_DD2816B566C5951B FOREIGN KEY (coupon_id) REFERENCES sylius_promotion_coupon (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE loyalty_points_entry ADD CONSTRAINT FK_DD2816B58D9F6D38 FOREIGN KEY (order_id) REFERENCES sylius_order (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE loyalty_points_entry DROP CONSTRAINT FK_DD2816B59395C3F3');
        $this->addSql('ALTER TABLE loyalty_points_entry DROP CONSTRAINT FK_DD2816B5E466ACA1');
        $this->addSql('ALTER TABLE loyalty_points_entry DROP CONSTRAINT FK_DD2816B566C5951B');
        $this->addSql('ALTER TABLE loyalty_points_entry DROP CONSTRAINT FK_DD2816B58D9F6D38');
        $this->addSql('DROP TABLE loyalty_points_entry');
        $this->addSql('DROP TABLE loyalty_reward');
    }
}
