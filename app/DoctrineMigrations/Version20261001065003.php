<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261001065003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add referral and referral_level tables, and referral_code/successful_referral_count on sylius_customer';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE referral (
              id SERIAL NOT NULL,
              referred_id INT NOT NULL,
              referrer_id INT NOT NULL,
              referrer_reward_coupon_id INT DEFAULT NULL,
              referred_welcome_coupon_id INT DEFAULT NULL,
              triggering_order_id INT DEFAULT NULL,
              status VARCHAR(16) DEFAULT 'pending' NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_73079D00CFE2A98 ON referral (referred_id)');
        $this->addSql('CREATE INDEX IDX_73079D00798C22DB ON referral (referrer_id)');
        $this->addSql('CREATE INDEX IDX_73079D00B014A4C8 ON referral (referrer_reward_coupon_id)');
        $this->addSql('CREATE INDEX IDX_73079D0091DAA87D ON referral (referred_welcome_coupon_id)');
        $this->addSql('CREATE INDEX IDX_73079D009B24967B ON referral (triggering_order_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE referral_level (
              id SERIAL NOT NULL,
              name VARCHAR(64) NOT NULL,
              position INT DEFAULT 0 NOT NULL,
              min_referral_count INT NOT NULL,
              reward_type VARCHAR(32) NOT NULL,
              reward_amount INT DEFAULT NULL,
              reward_percentage DOUBLE PRECISION DEFAULT NULL,
              coupon_validity_days INT DEFAULT 30 NOT NULL,
              usage_limit INT DEFAULT 1,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql(
            'ALTER TABLE referral ADD CONSTRAINT FK_73079D00CFE2A98 FOREIGN KEY (referred_id) REFERENCES sylius_customer (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE'
        );
        $this->addSql(
            'ALTER TABLE referral ADD CONSTRAINT FK_73079D00798C22DB FOREIGN KEY (referrer_id) REFERENCES sylius_customer (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE'
        );
        $this->addSql(
            'ALTER TABLE referral ADD CONSTRAINT FK_73079D00B014A4C8 FOREIGN KEY (referrer_reward_coupon_id) REFERENCES sylius_promotion_coupon (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE'
        );
        $this->addSql(
            'ALTER TABLE referral ADD CONSTRAINT FK_73079D0091DAA87D FOREIGN KEY (referred_welcome_coupon_id) REFERENCES sylius_promotion_coupon (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE'
        );
        $this->addSql(
            'ALTER TABLE referral ADD CONSTRAINT FK_73079D009B24967B FOREIGN KEY (triggering_order_id) REFERENCES sylius_order (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE'
        );
        $this->addSql('ALTER TABLE sylius_customer ADD referral_code VARCHAR(12) DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_customer ADD successful_referral_count INT DEFAULT 0 NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_7E82D5E66447454A ON sylius_customer (referral_code)');

        // Seed the default Bronze/Silver/Gold tiers so the feature ships with
        // sane, admin-editable thresholds instead of an empty levels table.
        $this->addSql(<<<'SQL'
            INSERT INTO referral_level (name, position, min_referral_count, reward_type, reward_amount, reward_percentage, coupon_validity_days, usage_limit, updated_at) VALUES
              ('bronze', 0, 1, 'order_fixed_discount', 500, NULL, 30, 1, NOW()),
              ('silver', 1, 5, 'order_fixed_discount', 1000, NULL, 30, 1, NOW()),
              ('gold', 2, 15, 'order_percentage_discount', NULL, 15, 30, 1, NOW())
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE referral DROP CONSTRAINT FK_73079D00CFE2A98');
        $this->addSql('ALTER TABLE referral DROP CONSTRAINT FK_73079D00798C22DB');
        $this->addSql('ALTER TABLE referral DROP CONSTRAINT FK_73079D00B014A4C8');
        $this->addSql('ALTER TABLE referral DROP CONSTRAINT FK_73079D0091DAA87D');
        $this->addSql('ALTER TABLE referral DROP CONSTRAINT FK_73079D009B24967B');
        $this->addSql('DROP TABLE referral');
        $this->addSql('DROP TABLE referral_level');
        $this->addSql('DROP INDEX UNIQ_7E82D5E66447454A');
        $this->addSql('ALTER TABLE sylius_customer DROP referral_code');
        $this->addSql('ALTER TABLE sylius_customer DROP successful_referral_count');
    }
}
