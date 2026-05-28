<?php

namespace AppBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CustomerSegmentationController extends AbstractController
{
    public function __construct(private readonly bool $rfmEnabled) {}

    private const ITEMS_PER_PAGE = 20;

    #[Route('/admin/customers/segmentation', name: 'admin_customer_segmentation', methods: ['GET'])]
    public function __invoke(Request $request, EntityManagerInterface $entityManager, PaginatorInterface $paginator): Response
    {
        if (!$this->rfmEnabled) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $conn = $entityManager->getConnection();
        $rows = $conn->executeQuery($this->buildSql())->fetchAllAssociative();

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
            'segment_cards'  => $segmentCards,
            'active_segment' => $activeSegment,
            'customers'      => $customers,
            'segment_meta'   => $segmentMeta,
        ]);
    }

    private function buildSql(): string
    {
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
                NTILE(4) OVER (ORDER BY last_order_at ASC) AS r_score,
                NTILE(4) OVER (ORDER BY frequency ASC)     AS f_score,
                NTILE(4) OVER (ORDER BY monetary ASC)      AS m_score
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
                ELSE 'need_attention'
            END AS segment
        FROM rfm_scores
        ORDER BY segment, id
        SQL;
    }

    private function getSegmentMeta(): array
    {
        return [
            'champions'           => ['style' => 'success', 'icon' => 'trophy'],
            'loyal_customers'     => ['style' => 'primary', 'icon' => 'star'],
            'potential_loyalists' => ['style' => 'info',    'icon' => 'thumbs-up'],
            'recent_customers'    => ['style' => 'info',    'icon' => 'clock-o'],
            'promising'           => ['style' => 'info',    'icon' => 'arrow-up'],
            'need_attention'      => ['style' => 'warning', 'icon' => 'exclamation-triangle'],
            'at_risk'             => ['style' => 'warning', 'icon' => 'exclamation-circle'],
            'cant_lose_them'      => ['style' => 'danger',  'icon' => 'fire'],
            'hibernating'         => ['style' => 'default', 'icon' => 'moon-o'],
            'lost'                => ['style' => 'danger',  'icon' => 'times-circle'],
        ];
    }
}
