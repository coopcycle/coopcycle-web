<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261001112844 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed default referral welcome-coupon settings, so the feature works out of the box without an admin saving the config form first';
    }

    public function up(Schema $schema): void
    {
        // Without this, referral_welcome_reward_amount stays unset until an
        // admin saves the welcome coupon form once -- ReferralRewardCouponFactory
        // then defaults it to 0, so the referred customer's welcome coupon
        // silently carries no discount at all. ON CONFLICT DO NOTHING so this
        // never clobbers values an admin may already have configured.
        $this->addSql(<<<'SQL'
            INSERT INTO craue_config_setting (name, value) VALUES
              ('referral_welcome_reward_type', 'order_fixed_discount'),
              ('referral_welcome_reward_amount', '500'),
              ('referral_welcome_coupon_validity_days', '30'),
              ('referral_pending_ttl_days', '30')
            ON CONFLICT (name) DO NOTHING
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DELETE FROM craue_config_setting WHERE name IN (
              'referral_welcome_reward_type',
              'referral_welcome_reward_amount',
              'referral_welcome_coupon_validity_days',
              'referral_pending_ttl_days'
            )
        SQL);
    }
}
