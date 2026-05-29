<?php

namespace AppBundle\Controller\Admin;

use AppBundle\Form\RfmThresholdsType;
use AppBundle\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CustomerSegmentationController extends AbstractController
{
    private const ITEMS_PER_PAGE = 20;

    private const R_DEFAULTS = [30, 90, 365];
    private const F_DEFAULTS = [2, 5, 10];

    public function __construct(
        private readonly bool            $rfmEnabled,
        private readonly SettingsManager $settingsManager,
    ) {}

    #[Route('/admin/customers/segmentation', name: 'admin_customer_segmentation', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, EntityManagerInterface $entityManager, PaginatorInterface $paginator): Response
    {
        if (!$this->rfmEnabled) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $thresholds = $this->getThresholds();
        $thresholdsForm = $this->createForm(RfmThresholdsType::class, $thresholds);
        $thresholdsForm->handleRequest($request);

        if ($thresholdsForm->isSubmitted() && $thresholdsForm->isValid()) {
            foreach ($thresholdsForm->getData() as $key => $value) {
                $this->settingsManager->set($key, (string) $value);
            }
            $this->settingsManager->flush();
            $this->addFlash('notice', 'rfm.thresholds.saved');

            return $this->redirectToRoute('admin_customer_segmentation');
        }

        $conn = $entityManager->getConnection();
        $rows = $conn->executeQuery($this->buildSql($thresholds))->fetchAllAssociative();

        $segments = [];
        foreach ($rows as $row) {
            $segments[$row['segment']][] = $row;
        }

        $segmentMeta = $this->getSegmentMeta();

        $segmentCards = [];
        foreach ($segmentMeta as $key => $meta) {
            $segmentCards[$key] = array_merge($meta, [
                'count' => isset($segments[$key]) ? count($segments[$key]) : 0,
            ]);
        }

        $activeSegment = $request->query->get('segment');
        if ($activeSegment !== null && !array_key_exists($activeSegment, $segmentMeta)) {
            $activeSegment = null;
        }

        $customers = $paginator->paginate(
            $activeSegment !== null ? ($segments[$activeSegment] ?? []) : [],
            $request->query->getInt('page', 1),
            self::ITEMS_PER_PAGE
        );

        return $this->render('admin/customer_segmentation.html.twig', [
            'segment_cards'   => $segmentCards,
            'active_segment'  => $activeSegment,
            'customers'       => $customers,
            'segment_meta'    => $segmentMeta,
            'chart_data'      => $this->buildChartData($rows),
            'thresholds_form' => $thresholdsForm,
        ]);
    }

    private function getThresholds(): array
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

    private function buildChartData(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        $segmentCounts   = [];
        $segmentMonetary = [];
        $quartileValues  = ['r' => [], 'f' => [], 'm' => []];
        $bubbleCells     = [];

        foreach ($rows as $row) {
            $seg = $row['segment'];

            $segmentCounts[$seg] = ($segmentCounts[$seg] ?? 0) + 1;

            $segmentMonetary[$seg]['sum']   = ($segmentMonetary[$seg]['sum']   ?? 0) + $row['monetary'];
            $segmentMonetary[$seg]['count'] = ($segmentMonetary[$seg]['count'] ?? 0) + 1;

            $daysAgo = (int) ceil((time() - strtotime($row['last_order_at'])) / 86400);
            $quartileValues['r'][$row['r_score']][] = $daysAgo;
            $quartileValues['f'][$row['f_score']][] = (int) $row['frequency'];
            $quartileValues['m'][$row['m_score']][] = (int) $row['monetary'];

            $key = $row['r_score'] . '_' . $row['f_score'];
            if (!isset($bubbleCells[$key])) {
                $bubbleCells[$key] = [
                    'r'       => (int) $row['r_score'],
                    'f'       => (int) $row['f_score'],
                    'segment' => $seg,
                    'count'   => 0,
                ];
            }
            $bubbleCells[$key]['count']++;
        }

        $quartileBounds = [];
        foreach ($quartileValues as $dim => $byScore) {
            ksort($byScore);
            foreach ($byScore as $score => $values) {
                $quartileBounds[$dim][$score] = [min($values), max($values)];
            }
        }

        $segmentAvgMonetary = array_map(
            fn($d) => (int) round($d['sum'] / $d['count']),
            $segmentMonetary
        );

        return [
            'segment_counts'       => $segmentCounts,
            'segment_avg_monetary' => $segmentAvgMonetary,
            'quartile_bounds'      => $quartileBounds,
            'bubble_cells'         => array_values($bubbleCells),
        ];
    }

    private function getSegmentMeta(): array
    {
        return [
            'champions'           => ['style' => 'success', 'icon' => 'trophy'],
            'loyal_customers'     => ['style' => 'primary', 'icon' => 'star'],
            'potential_loyalists' => ['style' => 'info',    'icon' => 'thumbs-up'],
            'recent_customers'    => ['style' => 'info',    'icon' => 'clock-o'],
            'promising'           => ['style' => 'info',    'icon' => 'arrow-up'],
            'at_risk'             => ['style' => 'warning', 'icon' => 'exclamation-circle'],
            'cant_lose_them'      => ['style' => 'danger',  'icon' => 'fire'],
            'hibernating'         => ['style' => 'default', 'icon' => 'moon-o'],
            'lost'                => ['style' => 'danger',  'icon' => 'times-circle'],
        ];
    }
}
