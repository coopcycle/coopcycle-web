<?php

namespace AppBundle\Controller\Admin;

use AppBundle\Form\RfmThresholdsType;
use AppBundle\Service\RfmSegmentCalculator;
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

    public function __construct(
        private readonly bool                 $rfmEnabled,
        private readonly SettingsManager      $settingsManager,
        private readonly RfmSegmentCalculator $rfmSegmentCalculator,
    ) {}

    #[Route('/admin/customers/segmentation', name: 'admin_customer_segmentation', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, EntityManagerInterface $entityManager, PaginatorInterface $paginator): Response
    {
        if (!$this->rfmEnabled) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $thresholds = $this->rfmSegmentCalculator->getThresholds();
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

        $rows = $this->rfmSegmentCalculator->computeRows($thresholds);

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
