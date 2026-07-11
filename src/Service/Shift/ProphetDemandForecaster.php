<?php

namespace AppBundle\Service\Shift;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Forecasts demand with Facebook Prophet, running in the Python recommender
 * service. Unlike the heuristic (which folds history into day-of-week/hour
 * buckets and only sees the last few weeks), Prophet is fitted on the dated
 * hourly series, so it captures trend, yearly seasonality and public holidays.
 *
 * Falls back to the heuristic whenever the service is unreachable or there
 * isn't enough usable history, so schedule generation always works.
 */
final class ProphetDemandForecaster implements DemandForecaster
{
    public const SOURCE = 'prophet';

    /**
     * Below ~4 weeks of real history, seasonality models are noise — let the
     * recency-weighted heuristic handle young instances.
     */
    private const MIN_HISTORY_DAYS = 28;

    // Fitting on 1-2 years of hourly buckets takes a few seconds
    private const REQUEST_TIMEOUT = 120;

    private string $lastSource = self::SOURCE;

    public function __construct(
        #[Autowire(service: 'recommender.client')] private readonly HttpClientInterface $recommenderClient,
        private readonly HeuristicDemandForecaster $fallback,
        private readonly LoggerInterface $logger,
        #[Autowire('%country_iso%')] private readonly string $country)
    {
    }

    public function forecast(array $samples, float $serviceLevel, ?\DateTimeImmutable $windowEnd = null, ?string $timezone = null): array
    {
        if (null === $windowEnd || null === $timezone) {
            // Without the window anchor the buckets can't be mapped back to
            // real dates, which is the whole point of Prophet
            return $this->fallbackForecast($samples, $serviceLevel, 'missing window/timezone context');
        }

        try {
            $series = $this->buildSeries($samples, $windowEnd, $timezone);

            if (!$this->hasEnoughHistory($series)) {
                return $this->fallbackForecast($samples, $serviceLevel, 'not enough usable history');
            }

            $response = $this->recommenderClient->request('POST', '/forecast/demand', [
                'timeout' => self::REQUEST_TIMEOUT,
                'json' => [
                    'series' => $series,
                    'horizon' => $this->buildHorizon($samples, $windowEnd),
                    'quantile' => $serviceLevel,
                    'country_holidays' => $this->country,
                ],
            ])->toArray();

            $this->lastSource = self::SOURCE;

            return $this->mapPredictions($samples, $response['predictions']);

        } catch (\Throwable $e) {
            return $this->fallbackForecast($samples, $serviceLevel, $e->getMessage());
        }
    }

    public function getLastSource(): string
    {
        return $this->lastSource;
    }

    /**
     * Reconstructs the dated hourly series from the (dow, hour, weeksAgo)
     * buckets. Week N ago spans [windowEnd - (N+1) weeks, windowEnd - N weeks),
     * and windowEnd is a Monday, so bucket (dow, hour, weeksAgo) is exactly one
     * wall-clock hour.
     *
     * Points in the future (the window may end past "now" when generating a
     * schedule several weeks ahead) and the all-zero prefix before the instance
     * had any deliveries are dropped — Prophet would read both as real zero
     * demand.
     *
     * @return array<int, array{ds: string, y: int}>
     */
    private function buildSeries(array $samples, \DateTimeImmutable $windowEnd, string $timezone): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone($timezone));

        $points = [];
        foreach ($samples as $dow => $hours) {
            foreach ($hours as $hour => $weekly) {
                foreach ($weekly as $weeksAgo => $count) {
                    $ds = $windowEnd
                        ->modify(sprintf('-%d days', ($weeksAgo + 1) * 7))
                        ->modify(sprintf('+%d days', $dow - 1))
                        ->setTime($hour, 0);

                    if ($ds->format('Y-m-d H:i:s') >= $now->format('Y-m-d H:i:s')) {
                        continue;
                    }

                    $points[$ds->format('Y-m-d H:i:s')] = $count;
                }
            }
        }

        ksort($points);

        // Trim the all-zero prefix (before the instance existed / used tasks)
        $firstActive = null;
        foreach ($points as $ds => $count) {
            if ($count > 0) {
                $firstActive = $ds;
                break;
            }
        }

        if (null === $firstActive) {
            return [];
        }

        $series = [];
        foreach ($points as $ds => $count) {
            if ($ds >= $firstActive) {
                $series[] = ['ds' => $ds, 'y' => $count];
            }
        }

        return $series;
    }

    /**
     * The timestamps to predict: every open hour of the target week (which
     * starts at windowEnd), taken from the zero-filled sample grid.
     *
     * @return string[]
     */
    private function buildHorizon(array $samples, \DateTimeImmutable $windowEnd): array
    {
        $horizon = [];
        foreach ($samples as $dow => $hours) {
            foreach (array_keys($hours) as $hour) {
                $horizon[] = $windowEnd
                    ->modify(sprintf('+%d days', $dow - 1))
                    ->setTime($hour, 0)
                    ->format('Y-m-d H:i:s');
            }
        }

        sort($horizon);

        return $horizon;
    }

    /**
     * @return array<int, array<int, float>> $forecast[$dow][$hour]
     */
    private function mapPredictions(array $samples, array $predictions): array
    {
        $forecast = [];
        foreach ($samples as $dow => $hours) {
            foreach (array_keys($hours) as $hour) {
                $forecast[$dow][$hour] = 0.0;
            }
        }

        foreach ($predictions as $prediction) {
            $ds = new \DateTimeImmutable($prediction['ds']);
            $dow = (int) $ds->format('N');
            $hour = (int) $ds->format('G');

            if (isset($forecast[$dow][$hour])) {
                $forecast[$dow][$hour] = max(0.0, (float) $prediction['yhat']);
            }
        }

        return $forecast;
    }

    private function hasEnoughHistory(array $series): bool
    {
        if (count($series) === 0) {
            return false;
        }

        $first = new \DateTimeImmutable($series[0]['ds']);
        $last = new \DateTimeImmutable($series[count($series) - 1]['ds']);

        return $last->diff($first)->days >= self::MIN_HISTORY_DAYS;
    }

    private function fallbackForecast(array $samples, float $serviceLevel, string $reason): array
    {
        $this->logger->warning(sprintf('Prophet demand forecast unavailable, falling back to heuristic: %s', $reason));

        $this->lastSource = HeuristicDemandForecaster::SOURCE;

        return $this->fallback->forecast($samples, $serviceLevel);
    }
}
