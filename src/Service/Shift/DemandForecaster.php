<?php

namespace AppBundle\Service\Shift;

interface DemandForecaster
{
    /**
     * Forecasts the expected demand (deliveries) per (day-of-week, hour) bucket.
     *
     * @param array<int, array<int, array<int, int>>> $samples
     *        $samples[$dow][$hour] = list of weekly counts, keyed by "weeks ago"
     *        (0 = most recent week). Buckets are zero-filled by the caller.
     * @param float $serviceLevel The demand quantile to staff for (e.g 0.8),
     *        so the schedule covers busy periods, not just the average.
     *
     * @return array<int, array<int, float>> $forecast[$dow][$hour] = predicted deliveries
     */
    public function forecast(array $samples, float $serviceLevel): array;
}
