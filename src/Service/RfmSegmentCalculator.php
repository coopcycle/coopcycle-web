<?php

namespace AppBundle\Service;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Computes RFM (Recency, Frequency, Monetary) segments for customers.
 *
 * Extracted so that both the segmentation page and the users export
 * share a single source of truth for the scoring thresholds and SQL.
 */
class RfmSegmentCalculator
{
    private const R_DEFAULTS = [30, 90, 365];
    private const F_DEFAULTS = [2, 5, 10];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SettingsManager $settingsManager,
    ) {}

    public function getThresholds(): array
    {
        return [
            'rfm_r_score4_max_days'   => (int) ($this->settingsManager->get('rfm_r_score4_max_days')   ?? self::R_DEFAULTS[0]),
            'rfm_r_score3_max_days'   => (int) ($this->settingsManager->get('rfm_r_score3_max_days')   ?? self::R_DEFAULTS[1]),
            'rfm_r_score2_max_days'   => (int) ($this->settingsManager->get('rfm_r_score2_max_days')   ?? self::R_DEFAULTS[2]),
            'rfm_f_score4_min_orders' => (int) ($this->settingsManager->get('rfm_f_score4_min_orders') ?? self::F_DEFAULTS[2]),
            'rfm_f_score3_min_orders' => (int) ($this->settingsManager->get('rfm_f_score3_min_orders') ?? self::F_DEFAULTS[1]),
            'rfm_f_score2_min_orders' => (int) ($this->settingsManager->get('rfm_f_score2_min_orders') ?? self::F_DEFAULTS[0]),
        ];
    }

    /**
     * @return array<int, array<string, mixed>> One row per customer with its RFM scores and segment.
     */
    public function computeRows(?array $thresholds = null): array
    {
        $thresholds = $thresholds ?? $this->getThresholds();

        return $this->entityManager
            ->getConnection()
            ->executeQuery($this->buildSql($thresholds))
            ->fetchAllAssociative();
    }

    /**
     * @return array<string, string> Map of username => segment.
     */
    public function getSegmentsByUsername(): array
    {
        $map = [];
        foreach ($this->computeRows() as $row) {
            if ($row['username'] !== null && $row['segment'] !== null) {
                $map[$row['username']] = $row['segment'];
            }
        }

        return $map;
    }

    private function buildSql(array $t): string
    {
        $r4 = $t['rfm_r_score4_max_days'];
        $r3 = $t['rfm_r_score3_max_days'];
        $r2 = $t['rfm_r_score2_max_days'];
        $f4 = $t['rfm_f_score4_min_orders'];
        $f3 = $t['rfm_f_score3_min_orders'];
        $f2 = $t['rfm_f_score2_min_orders'];

        return <<<SQL
        WITH rfm_data AS (
            SELECT
                c.id,
                c.email_canonical                                          AS email,
                c.first_name,
                c.last_name,
                u.username,
                COUNT(o.id)::integer                                       AS frequency,
                SUM(o.total)::integer                                      AS monetary,
                MAX(o.created_at)                                          AS last_order_at
            FROM sylius_customer c
            INNER JOIN sylius_order o ON o.customer_id = c.id
            LEFT  JOIN api_user u     ON u.customer_id = c.id
            WHERE o.state = 'fulfilled'
              AND EXISTS (SELECT 1 FROM sylius_order_vendor ov WHERE ov.order_id = o.id)
            GROUP BY c.id, c.email_canonical, c.first_name, c.last_name, u.username
        ),
        rfm_scores AS (
            SELECT *,
                CASE
                    WHEN DATE_PART('day', NOW() - last_order_at) <= $r4 THEN 4
                    WHEN DATE_PART('day', NOW() - last_order_at) <= $r3 THEN 3
                    WHEN DATE_PART('day', NOW() - last_order_at) <= $r2 THEN 2
                    ELSE 1
                END AS r_score,
                CASE
                    WHEN frequency >= $f4 THEN 4
                    WHEN frequency >= $f3 THEN 3
                    WHEN frequency >= $f2 THEN 2
                    ELSE 1
                END AS f_score,
                NTILE(4) OVER (ORDER BY monetary ASC)                      AS m_score
            FROM rfm_data
        )
        SELECT
            id, email, first_name, last_name, username,
            frequency, monetary, last_order_at,
            r_score, f_score, m_score,
            CASE
                WHEN r_score = 4 AND f_score = 4       THEN 'champions'
                WHEN r_score = 1 AND f_score = 4       THEN 'cant_lose_them'
                WHEN r_score <= 2 AND f_score >= 3     THEN 'at_risk'
                WHEN r_score >= 2 AND f_score >= 3     THEN 'loyal_customers'
                WHEN r_score = 4 AND f_score = 1       THEN 'recent_customers'
                WHEN r_score = 3 AND f_score = 1       THEN 'promising'
                WHEN r_score >= 3 AND f_score = 2      THEN 'potential_loyalists'
                WHEN r_score = 1 AND f_score = 1       THEN 'lost'
                WHEN r_score <= 2 AND f_score <= 2     THEN 'hibernating'
            END AS segment
        FROM rfm_scores
        ORDER BY segment, id
        SQL;
    }
}
