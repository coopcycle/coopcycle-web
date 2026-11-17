<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Gift cards: the card itself, its ledger, the Stripe charges backing it, and
 * a record of what the platform transfers out to restaurants.
 *
 * `order_transfer` is not gift-card-specific. Transfers to connected accounts
 * have been fire-and-forget until now -- created and the id discarded -- which
 * leaves nothing to reverse on a refund and no way to tell a replayed webhook
 * from a genuine second payout. Gift cards make that gap unaffordable, so the
 * record starts here and hub transfers move onto it too.
 */
final class Version20261117132753 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add gift cards, their ledger, and a record of transfers to connected accounts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE gift_card (id SERIAL NOT NULL, purchaser_id INT DEFAULT NULL, restaurant_id INT DEFAULT NULL, code VARCHAR(32) NOT NULL, currency_code VARCHAR(3) NOT NULL, livemode BOOLEAN DEFAULT false NOT NULL, initial_amount INT NOT NULL, balance INT DEFAULT 0 NOT NULL, state VARCHAR(16) NOT NULL, funding VARCHAR(16) DEFAULT \'funded\' NOT NULL, purchaser_email VARCHAR(255) DEFAULT NULL, recipient_email VARCHAR(255) DEFAULT NULL, recipient_name VARCHAR(255) DEFAULT NULL, message TEXT DEFAULT NULL, purchase_payment_intent_id VARCHAR(255) DEFAULT NULL, purchase_charge_id VARCHAR(255) DEFAULT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, issued_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_E4696A8EED255ED6 ON gift_card (purchaser_id)');
        $this->addSql('CREATE INDEX IDX_E4696A8EB1E7706E ON gift_card (restaurant_id)');
        $this->addSql('CREATE INDEX idx_gift_card_expiry ON gift_card (state, expires_at)');
        $this->addSql('CREATE INDEX idx_gift_card_recipient_email ON gift_card (recipient_email)');
        $this->addSql('CREATE INDEX idx_gift_card_purchaser_email ON gift_card (purchaser_email)');
        $this->addSql('CREATE UNIQUE INDEX uniq_gift_card_code ON gift_card (code)');
        $this->addSql('CREATE UNIQUE INDEX uniq_gift_card_purchase_charge ON gift_card (purchase_charge_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_gift_card_purchase_intent ON gift_card (purchase_payment_intent_id)');

        $this->addSql('CREATE TABLE gift_card_transaction (id SERIAL NOT NULL, gift_card_id INT NOT NULL, payment_id INT DEFAULT NULL, parent_id INT DEFAULT NULL, order_id INT DEFAULT NULL, type VARCHAR(16) NOT NULL, amount INT NOT NULL, stripe_transfer_id VARCHAR(255) DEFAULT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_5D2F3A302696A98F ON gift_card_transaction (gift_card_id)');
        $this->addSql('CREATE INDEX IDX_5D2F3A304C3A3BB ON gift_card_transaction (payment_id)');
        $this->addSql('CREATE INDEX IDX_5D2F3A30727ACA70 ON gift_card_transaction (parent_id)');
        $this->addSql('CREATE INDEX idx_gift_card_transaction_card ON gift_card_transaction (gift_card_id, created_at)');
        $this->addSql('CREATE INDEX idx_gift_card_transaction_payment ON gift_card_transaction (payment_id, type)');
        $this->addSql('CREATE INDEX idx_gift_card_transaction_order ON gift_card_transaction (order_id)');
        $this->addSql('CREATE INDEX idx_gift_card_transaction_hold_expiry ON gift_card_transaction (type, expires_at)');

        $this->addSql('CREATE TABLE gift_card_purchase_charge (id SERIAL NOT NULL, gift_card_id INT NOT NULL, stripe_charge_id VARCHAR(255) NOT NULL, amount INT NOT NULL, currency_code VARCHAR(3) NOT NULL, amount_allocated INT DEFAULT 0 NOT NULL, amount_refunded INT DEFAULT 0 NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_E63A87912696A98F ON gift_card_purchase_charge (gift_card_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_gift_card_purchase_charge_id ON gift_card_purchase_charge (stripe_charge_id)');

        $this->addSql('CREATE TABLE order_transfer (id SERIAL NOT NULL, restaurant_id INT DEFAULT NULL, order_id INT NOT NULL, stripe_transfer_id VARCHAR(255) DEFAULT NULL, amount INT NOT NULL, currency_code VARCHAR(3) NOT NULL, source_transaction VARCHAR(255) DEFAULT NULL, transfer_group VARCHAR(255) NOT NULL, idempotency_key VARCHAR(255) NOT NULL, state VARCHAR(32) NOT NULL, reversed_amount INT DEFAULT 0 NOT NULL, failure_code VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_A1CC4171B1E7706E ON order_transfer (restaurant_id)');
        $this->addSql('CREATE INDEX IDX_A1CC41718D9F6D38 ON order_transfer (order_id)');
        $this->addSql('CREATE INDEX idx_order_transfer_state ON order_transfer (state, created_at)');
        $this->addSql('CREATE INDEX idx_order_transfer_group ON order_transfer (transfer_group)');
        $this->addSql('CREATE UNIQUE INDEX uniq_order_transfer_idempotency ON order_transfer (order_id, idempotency_key)');
        $this->addSql('CREATE UNIQUE INDEX uniq_order_transfer_stripe_id ON order_transfer (stripe_transfer_id)');

        $this->addSql('ALTER TABLE gift_card ADD CONSTRAINT FK_E4696A8EED255ED6 FOREIGN KEY (purchaser_id) REFERENCES sylius_customer (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE gift_card ADD CONSTRAINT FK_E4696A8EB1E7706E FOREIGN KEY (restaurant_id) REFERENCES restaurant (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE gift_card_transaction ADD CONSTRAINT FK_5D2F3A302696A98F FOREIGN KEY (gift_card_id) REFERENCES gift_card (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE gift_card_transaction ADD CONSTRAINT FK_5D2F3A304C3A3BB FOREIGN KEY (payment_id) REFERENCES sylius_payment (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE gift_card_transaction ADD CONSTRAINT FK_5D2F3A30727ACA70 FOREIGN KEY (parent_id) REFERENCES gift_card_transaction (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE gift_card_transaction ADD CONSTRAINT FK_5D2F3A308D9F6D38 FOREIGN KEY (order_id) REFERENCES sylius_order (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE gift_card_purchase_charge ADD CONSTRAINT FK_E63A87912696A98F FOREIGN KEY (gift_card_id) REFERENCES gift_card (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE order_transfer ADD CONSTRAINT FK_A1CC4171B1E7706E FOREIGN KEY (restaurant_id) REFERENCES restaurant (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE order_transfer ADD CONSTRAINT FK_A1CC41718D9F6D38 FOREIGN KEY (order_id) REFERENCES sylius_order (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE sylius_order ADD gift_card_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_order ADD CONSTRAINT FK_6196A1F92696A98F FOREIGN KEY (gift_card_id) REFERENCES gift_card (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_6196A1F92696A98F ON sylius_order (gift_card_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_order DROP CONSTRAINT FK_6196A1F92696A98F');
        $this->addSql('DROP INDEX IDX_6196A1F92696A98F');
        $this->addSql('ALTER TABLE sylius_order DROP gift_card_id');

        $this->addSql('ALTER TABLE gift_card DROP CONSTRAINT FK_E4696A8EED255ED6');
        $this->addSql('ALTER TABLE gift_card DROP CONSTRAINT FK_E4696A8EB1E7706E');
        $this->addSql('ALTER TABLE gift_card_transaction DROP CONSTRAINT FK_5D2F3A302696A98F');
        $this->addSql('ALTER TABLE gift_card_transaction DROP CONSTRAINT FK_5D2F3A304C3A3BB');
        $this->addSql('ALTER TABLE gift_card_transaction DROP CONSTRAINT FK_5D2F3A30727ACA70');
        $this->addSql('ALTER TABLE gift_card_transaction DROP CONSTRAINT FK_5D2F3A308D9F6D38');
        $this->addSql('ALTER TABLE gift_card_purchase_charge DROP CONSTRAINT FK_E63A87912696A98F');
        $this->addSql('ALTER TABLE order_transfer DROP CONSTRAINT FK_A1CC4171B1E7706E');
        $this->addSql('ALTER TABLE order_transfer DROP CONSTRAINT FK_A1CC41718D9F6D38');

        $this->addSql('DROP TABLE order_transfer');
        $this->addSql('DROP TABLE gift_card_purchase_charge');
        $this->addSql('DROP TABLE gift_card_transaction');
        $this->addSql('DROP TABLE gift_card');
    }
}
