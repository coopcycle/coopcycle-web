<?php

namespace AppBundle\Service\Shift;

/**
 * A dependency-free forecaster that captures weekly seasonality by treating each
 * (day-of-week, hour) bucket independently. Recent weeks are weighted more
 * heavily, and demand is staffed to a service level (a quantile) rather than the
 * mean, using a normal approximation: predicted = mean + z(serviceLevel) * stddev.
 *
 * This is Phase 1. A Phase 2 forecaster can implement the same interface backed
 * by the Python service (Prophet) to add yearly seasonality & public holidays.
 */
final class HeuristicDemandForecaster implements DemandForecaster
{
    /**
     * How fast older weeks lose influence. A 4-week half-life means a sample
     * from 4 weeks ago counts half as much as last week's.
     */
    private const HALF_LIFE_WEEKS = 4.0;

    public function forecast(array $samples, float $serviceLevel): array
    {
        $z = self::inverseNormalCdf($serviceLevel);

        $forecast = [];

        foreach ($samples as $dow => $hours) {
            foreach ($hours as $hour => $weekly) {
                [$mean, $stdDev] = $this->weightedMeanAndStdDev($weekly);
                $forecast[$dow][$hour] = max(0.0, $mean + $z * $stdDev);
            }
        }

        return $forecast;
    }

    /**
     * @param array<int, int> $weekly count keyed by "weeks ago"
     * @return array{float, float} [mean, stdDev]
     */
    private function weightedMeanAndStdDev(array $weekly): array
    {
        $weightSum = 0.0;
        $weightedSum = 0.0;

        foreach ($weekly as $weeksAgo => $count) {
            $weight = pow(0.5, $weeksAgo / self::HALF_LIFE_WEEKS);
            $weightSum += $weight;
            $weightedSum += $weight * $count;
        }

        if ($weightSum <= 0.0) {
            return [0.0, 0.0];
        }

        $mean = $weightedSum / $weightSum;

        $varianceSum = 0.0;
        foreach ($weekly as $weeksAgo => $count) {
            $weight = pow(0.5, $weeksAgo / self::HALF_LIFE_WEEKS);
            $varianceSum += $weight * pow($count - $mean, 2);
        }

        return [$mean, sqrt($varianceSum / $weightSum)];
    }

    /**
     * Inverse of the standard normal CDF (Acklam's algorithm).
     * Maps a probability (service level) to a z-score, e.g 0.8 -> ~0.8416.
     */
    public static function inverseNormalCdf(float $p): float
    {
        if ($p <= 0.0) {
            return -INF;
        }
        if ($p >= 1.0) {
            return INF;
        }

        $a = [-3.969683028665376e+01, 2.209460984245205e+02, -2.759285104469687e+02, 1.383577518672690e+02, -3.066479806614716e+01, 2.506628277459239e+00];
        $b = [-5.447609879822406e+01, 1.615858368580409e+02, -1.556989798598866e+02, 6.680131188771972e+01, -1.328068155288572e+01];
        $c = [-7.784894002430293e-03, -3.223964580411365e-01, -2.400758277161838e+00, -2.549732539343734e+00, 4.374664141464968e+00, 2.938163982698783e+00];
        $d = [7.784695709041462e-03, 3.224671290700398e-01, 2.445134137142996e+00, 3.754408661907416e+00];

        $pLow = 0.02425;
        $pHigh = 1 - $pLow;

        if ($p < $pLow) {
            $q = sqrt(-2 * log($p));

            return ((((($c[0] * $q + $c[1]) * $q + $c[2]) * $q + $c[3]) * $q + $c[4]) * $q + $c[5]) /
                (((($d[0] * $q + $d[1]) * $q + $d[2]) * $q + $d[3]) * $q + 1);
        }

        if ($p <= $pHigh) {
            $q = $p - 0.5;
            $r = $q * $q;

            return ((((($a[0] * $r + $a[1]) * $r + $a[2]) * $r + $a[3]) * $r + $a[4]) * $r + $a[5]) * $q /
                ((((($b[0] * $r + $b[1]) * $r + $b[2]) * $r + $b[3]) * $r + $b[4]) * $r + 1);
        }

        $q = sqrt(-2 * log(1 - $p));

        return -((((($c[0] * $q + $c[1]) * $q + $c[2]) * $q + $c[3]) * $q + $c[4]) * $q + $c[5]) /
            (((($d[0] * $q + $d[1]) * $q + $d[2]) * $q + $d[3]) * $q + 1);
    }
}
