<?php

namespace AppBundle\Service\Shift;

use AppBundle\Entity\Shift;
use AppBundle\Entity\TaskRepository;
use AppBundle\Service\SettingsManager;

/**
 * Builds a demand-driven, pre-filled shift schedule for a target week from
 * historical delivery demand:
 *
 *   history -> forecast (per hour, at a service level) -> couriers needed
 *           -> staggered shift blocks -> proposed (unsaved) shifts + a demand
 *              vs coverage curve for the UI overlay.
 */
final class ScheduleGenerator
{
    public const DEFAULTS = [
        // Two years, so the Prophet forecaster can learn yearly seasonality.
        // The heuristic fallback is unaffected: its recency weighting makes
        // anything older than a couple of months negligible.
        'lookbackWeeks' => 104,
        // Deliveries a single courier can complete per hour
        'throughput' => 2.5,
        // Demand quantile to staff for (0.8 = cover a busy week, not just average)
        'serviceLevel' => 0.8,
        'openHour' => 8,
        'closeHour' => 22,
        'minShiftHours' => 3,
        'maxShiftHours' => 8,
    ];

    // Demand-driven shifts are delivery ("drive") shifts
    private const SHIFT_TYPE = Shift::TYPE_DRIVE;

    public function __construct(
        private readonly TaskRepository $taskRepository,
        private readonly DemandForecaster $forecaster,
        private readonly ShiftBuilder $shiftBuilder,
        private readonly SettingsManager $settingsManager)
    {
    }

    public function generate(\DateTimeImmutable $targetMonday): array
    {
        $config = $this->config();
        $timezone = $this->settingsManager->get('timezone') ?: date_default_timezone_get();

        $windowEnd = $targetMonday->setTime(0, 0);

        $samples = $this->taskRepository->getDropoffDemandSamples(
            $windowEnd,
            $config['lookbackWeeks'],
            $timezone,
            $config['openHour'],
            $config['closeHour']
        );

        $forecast = $this->forecaster->forecast($samples, $config['serviceLevel'], $windowEnd, $timezone);

        $shifts = [];
        $days = [];
        $totalObservations = 0;

        for ($dow = 1; $dow <= 7; $dow++) {
            $date = $targetMonday->modify(sprintf('+%d days', $dow - 1));

            $needByHour = [];
            $buckets = [];

            for ($hour = $config['openHour']; $hour < $config['closeHour']; $hour++) {
                $predictedDeliveries = $forecast[$dow][$hour] ?? 0.0;
                // Convert demand into courier-equivalent units so the overlay chart
                // compares demand & coverage on the same axis
                $demandCouriers = $predictedDeliveries / $config['throughput'];
                $needByHour[$hour] = (int) ceil($demandCouriers - 1e-9);

                $buckets[$hour] = [
                    'hour' => $hour,
                    'demand' => round($demandCouriers, 2),
                    'coverage' => 0,
                ];

                foreach ($samples[$dow][$hour] as $count) {
                    $totalObservations += $count;
                }
            }

            $blocks = $this->shiftBuilder->buildDay(
                $needByHour,
                $config['openHour'],
                $config['closeHour'],
                $config['minShiftHours'],
                $config['maxShiftHours']
            );

            foreach ($blocks as $block) {
                for ($hour = $block['start']; $hour < $block['end']; $hour++) {
                    $buckets[$hour]['coverage'] += $block['slots'];
                }

                $shifts[] = [
                    'type' => self::SHIFT_TYPE,
                    // Wall-clock local time, matching how the planning UI handles shifts
                    'startsAt' => sprintf('%sT%02d:00:00', $date->format('Y-m-d'), $block['start']),
                    'endsAt' => sprintf('%sT%02d:00:00', $date->format('Y-m-d'), $block['end']),
                    'slots' => $block['slots'],
                ];
            }

            $days[] = [
                'date' => $date->format('Y-m-d'),
                'dow' => $dow,
                'buckets' => array_values($buckets),
            ];
        }

        return [
            'shifts' => $shifts,
            'days' => $days,
            'meta' => [
                'lookbackWeeks' => $config['lookbackWeeks'],
                'serviceLevel' => $config['serviceLevel'],
                'throughput' => $config['throughput'],
                'observations' => $totalObservations,
                'forecaster' => $this->forecaster->getLastSource(),
            ],
        ];
    }

    private function config(): array
    {
        $stored = [];
        $json = $this->settingsManager->get('shift_planning_config');
        if (!empty($json)) {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $stored = $decoded;
            }
        }

        return array_merge(self::DEFAULTS, array_intersect_key($stored, self::DEFAULTS));
    }
}
