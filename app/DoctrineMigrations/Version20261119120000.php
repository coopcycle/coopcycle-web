<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * CustomerRewardCouponFactory used to write the admin-entered percentage
 * (10 = 10%) straight into the promotion action configuration, but
 * PercentageDiscountPromotionActionCommand::execute() multiplies the order
 * total by configuration['percentage'] -- it wants a 0..1 ratio. Every
 * referral/loyalty percentage reward minted before the fix is therefore
 * worth 100x what was configured.
 *
 * Scoped to promotions carrying an internal coupon: PromotionCoupon::$internal
 * is only ever set by CustomerRewardCouponFactory, so this leaves promotions
 * created by hand in /admin/promotions (whose form is a Symfony PercentType,
 * which already stores a ratio) alone.
 */
final class Version20261119120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rescale referral/loyalty percentage discounts from 0..100 to the 0..1 ratio Sylius expects';
    }

    public function up(Schema $schema): void
    {
        // The "> 1" guard makes this idempotent and keeps it from touching
        // coupons minted by the already-fixed code, in case the application
        // is deployed before this migration runs. A legitimate internal
        // reward is never above 1 -- that would be more than 100% off.
        //
        // A configured 100% is stored as 1.0 either side of the fix, so it is
        // correct as-is and rightly skipped here.
        $this->addSql(<<<'SQL'
            UPDATE sylius_promotion_action a
            SET configuration = jsonb_set(
                a.configuration,
                '{percentage}',
                to_jsonb(trim_scale(round((a.configuration->>'percentage')::numeric / 100, 4)))
            )
            WHERE a.type = 'order_percentage_discount'
              AND jsonb_typeof(a.configuration->'percentage') = 'number'
              AND (a.configuration->>'percentage')::numeric > 1
              AND EXISTS (
                SELECT 1 FROM sylius_promotion_coupon c
                WHERE c.promotion_id = a.promotion_id AND c.internal = true
              )
            SQL);
    }

    public function down(Schema $schema): void
    {
        // Mirror image, with the same caveat about 1.0: a 100% reward stays
        // 1.0 rather than becoming 100, since that is what both the old and
        // the new code write for it.
        $this->addSql(<<<'SQL'
            UPDATE sylius_promotion_action a
            SET configuration = jsonb_set(
                a.configuration,
                '{percentage}',
                to_jsonb(trim_scale((a.configuration->>'percentage')::numeric * 100))
            )
            WHERE a.type = 'order_percentage_discount'
              AND jsonb_typeof(a.configuration->'percentage') = 'number'
              AND (a.configuration->>'percentage')::numeric < 1
              AND EXISTS (
                SELECT 1 FROM sylius_promotion_coupon c
                WHERE c.promotion_id = a.promotion_id AND c.internal = true
              )
            SQL);
    }
}
