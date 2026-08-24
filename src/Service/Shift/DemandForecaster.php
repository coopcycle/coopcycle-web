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
     * @param \DateTimeImmutable|null $windowEnd The Monday (local wall-clock) the
     *        sample window ends at — also the start of the week being forecast.
     *        Lets date-aware forecasters reconstruct real timestamps from the
     *        "weeks ago" buckets (for yearly seasonality & holidays).
     * @param string|null $timezone The instance timezone the samples were
     *        bucketed in.
     *
     * @return array<int, array<int, float>> $forecast[$dow][$hour] = predicted deliveries
     */
    public function forecast(array $samples, float $serviceLevel, ?\DateTimeImmutable $windowEnd = null, ?string $timezone = null): array;

    /**
     * Which engine actually produced the last forecast() result (e.g "prophet",
     * "heuristic") — implementations may silently fall back, and the UI tells
     * the dispatcher which one they're looking at.
     */
    public function getLastSource(): string;
}
