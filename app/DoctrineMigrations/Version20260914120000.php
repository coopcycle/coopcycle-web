<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Backfill sylius_adjustment.details['base'] on historical tax adjustments.
 *
 * OrderTaxesProcessor now stores, on every product tax adjustment it
 * creates, the exact base (incl. tax) amount its tax was calculated from —
 * see AppBundle\Sylius\OrderProcessing\OrderTaxesProcessor::createAdjustmentWithRate().
 * This matters because a single order item can carry several tax
 * adjustments at different rates (ZeltyMenuVatVentilator splits a bundled
 * menu's price across each component's rate on the *same* item), so the
 * item's own total isn't enough downstream to know each rate's share.
 * Without the base stored, excl-tax-per-rate reporting
 * (AppBundle\Utils\RestaurantStats) had to reconstruct it, which is exact
 * for a "normal" (single-rate) item but only an estimate for a ventilated
 * one — see that class for the full story.
 *
 * This backfills `details` on adjustments created before that change:
 *
 *   - Single-rate order item (the vast majority): its tax adjustment's base
 *     is exactly the order item's own total. Not an estimate — this is
 *     exact, since there is nothing to split.
 *   - Ventilated order item (several tax adjustments, one item): the true
 *     original per-rate split was never persisted and can't be recovered.
 *     This applies the same estimate RestaurantStats already computed on
 *     the fly — weight each rate's share by tax ÷ rate, last rate absorbs
 *     the rounding remainder so the shares still sum to exactly the item's
 *     total. Freezing that estimate here just means every adjustment is
 *     read the same simple way from now on; it does not make the estimate
 *     any truer than it already was.
 *
 * NB: this is a data migration and runs its statements directly, so it
 * reports real row counts — but `--dry-run` will not hold it back.
 */
final class Version20260914120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Backfill sylius_adjustment.details[base] on historical product tax adjustments (executes immediately, not dry-run safe)';
    }

    public function up(Schema $schema): void
    {
        $singleRate = $this->connection->executeStatement(<<<'SQL'
            UPDATE sylius_adjustment a
            SET details = json_build_object('base', oi.total)
            FROM sylius_order_item oi
            WHERE oi.id = a.order_item_id
              AND a.type = 'tax'
              AND a.order_item_id IS NOT NULL
              AND NOT jsonb_exists(a.details::jsonb, 'base')
              AND (
                    SELECT COUNT(*) FROM sylius_adjustment a2
                    WHERE a2.order_item_id = a.order_item_id AND a2.type = 'tax'
                  ) = 1
            SQL);

        $this->write(sprintf('Backfilled %d single-rate tax adjustment(s) — exact.', $singleRate));

        $ventilated = $this->connection->executeStatement(<<<'SQL'
            WITH candidates AS (
                SELECT
                    a.id                                                       AS adjustment_id,
                    a.order_item_id,
                    a.amount,
                    COALESCE(tr.amount, 0)                                     AS rate,
                    oi.total                                                   AS item_total,
                    ROW_NUMBER() OVER (PARTITION BY a.order_item_id ORDER BY a.id) AS rn,
                    COUNT(*)     OVER (PARTITION BY a.order_item_id)              AS cnt,
                    SUM(a.amount) OVER (PARTITION BY a.order_item_id)             AS item_tax_total
                FROM sylius_adjustment a
                JOIN sylius_order_item oi ON oi.id = a.order_item_id
                LEFT JOIN sylius_tax_rate tr ON tr.code = a.origin_code
                WHERE a.type = 'tax'
                  AND a.order_item_id IS NOT NULL
                  AND NOT jsonb_exists(a.details::jsonb, 'base')
            ),
            ventilated AS (
                SELECT *,
                    (item_total - item_tax_total)                              AS item_excl,
                    CASE WHEN rate > 0 THEN amount::numeric / rate ELSE 0 END   AS weight
                FROM candidates
                WHERE cnt > 1
            ),
            weighted AS (
                SELECT *,
                    SUM(weight) OVER (PARTITION BY order_item_id)              AS total_weight
                FROM ventilated
            ),
            provisional AS (
                SELECT
                    adjustment_id, order_item_id, amount, rn, cnt, item_excl,
                    CASE WHEN total_weight > 0
                         THEN ROUND(item_excl * weight / total_weight)
                         ELSE 0
                    END                                                        AS provisional_excl_share
                FROM weighted
            ),
            remainder AS (
                SELECT order_item_id,
                    SUM(provisional_excl_share) FILTER (WHERE rn < cnt)       AS allocated_before_last
                FROM provisional
                GROUP BY order_item_id
            ),
            final_shares AS (
                SELECT
                    p.adjustment_id,
                    p.amount,
                    CASE WHEN p.rn = p.cnt
                         THEN p.item_excl - COALESCE(r.allocated_before_last, 0)
                         ELSE p.provisional_excl_share
                    END                                                        AS excl_share
                FROM provisional p
                JOIN remainder r ON r.order_item_id = p.order_item_id
            )
            UPDATE sylius_adjustment a
            SET details = json_build_object('base', fs.excl_share + fs.amount)
            FROM final_shares fs
            WHERE a.id = fs.adjustment_id
            SQL);

        $this->write(sprintf('Backfilled %d ventilated tax adjustment(s) — estimated split.', $ventilated));

        $left = $this->connection->fetchOne(<<<'SQL'
            SELECT COUNT(*)
            FROM sylius_adjustment a
            WHERE a.type = 'tax'
              AND a.order_item_id IS NOT NULL
              AND NOT jsonb_exists(a.details::jsonb, 'base')
            SQL);

        if (0 === (int) $left) {
            $this->write('Every order-item tax adjustment now has details[base].');
        } else {
            $this->write(sprintf('WARNING: %d order-item tax adjustment(s) still have no details[base].', $left));
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'Backfilled details[base] cannot be distinguished from details set some other way.'
        );
    }
}
