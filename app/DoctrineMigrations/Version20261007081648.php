<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261007081648 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Default the referral program to free-delivery rewards -- the platform absorbs that cost, unlike a fixed/percentage discount which eats into the restaurant\'s revenue';
    }

    public function up(Schema $schema): void
    {
        // Only touches rows still holding the exact values seeded by
        // Version20261001065003, so an admin who already customized a level
        // keeps their configuration.
        $this->addSql(<<<'SQL'
            UPDATE referral_level SET reward_type = 'delivery_percentage_discount', reward_amount = NULL, usage_limit = 1
            WHERE name = 'bronze' AND reward_type = 'order_fixed_discount' AND reward_amount = 500 AND usage_limit = 1
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE referral_level SET reward_type = 'delivery_percentage_discount', reward_amount = NULL, usage_limit = 3
            WHERE name = 'silver' AND reward_type = 'order_fixed_discount' AND reward_amount = 1000 AND usage_limit = 1
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE referral_level SET reward_type = 'delivery_percentage_discount', reward_percentage = NULL, usage_limit = 5
            WHERE name = 'gold' AND reward_type = 'order_percentage_discount' AND reward_percentage = 15 AND usage_limit = 1
        SQL);

        // Same guard for the welcome-coupon global setting, seeded by
        // Version20261001112844.
        $this->addSql(<<<'SQL'
            UPDATE craue_config_setting SET value = 'delivery_percentage_discount'
            WHERE name = 'referral_welcome_reward_type' AND value = 'order_fixed_discount'
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE referral_level SET reward_type = 'order_fixed_discount', reward_amount = 500, usage_limit = 1
            WHERE name = 'bronze' AND reward_type = 'delivery_percentage_discount' AND usage_limit = 1
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE referral_level SET reward_type = 'order_fixed_discount', reward_amount = 1000, usage_limit = 1
            WHERE name = 'silver' AND reward_type = 'delivery_percentage_discount' AND usage_limit = 3
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE referral_level SET reward_type = 'order_percentage_discount', reward_percentage = 15, usage_limit = 1
            WHERE name = 'gold' AND reward_type = 'delivery_percentage_discount' AND usage_limit = 5
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE craue_config_setting SET value = 'order_fixed_discount'
            WHERE name = 'referral_welcome_reward_type' AND value = 'delivery_percentage_discount'
        SQL);
    }
}
