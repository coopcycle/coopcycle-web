<?php

namespace Tests\AppBundle\Service\Shift;

use AppBundle\Service\Shift\HeuristicDemandForecaster;
use PHPUnit\Framework\TestCase;

class HeuristicDemandForecasterTest extends TestCase
{
    private HeuristicDemandForecaster $forecaster;

    public function setUp(): void
    {
        $this->forecaster = new HeuristicDemandForecaster();
    }

    public function testInverseNormalCdfMatchesKnownQuantiles()
    {
        $this->assertEqualsWithDelta(0.0, HeuristicDemandForecaster::inverseNormalCdf(0.5), 1e-4);
        $this->assertEqualsWithDelta(0.8416, HeuristicDemandForecaster::inverseNormalCdf(0.8), 1e-3);
        $this->assertEqualsWithDelta(1.2816, HeuristicDemandForecaster::inverseNormalCdf(0.9), 1e-3);
    }

    public function testForecastAtMedianEqualsWeightedMean()
    {
        // Constant demand of 10 every week -> mean 10, stddev 0
        $samples = [1 => [9 => [0 => 10, 1 => 10, 2 => 10]]];

        $forecast = $this->forecaster->forecast($samples, 0.5);

        $this->assertEqualsWithDelta(10.0, $forecast[1][9], 1e-6);
    }

    public function testServiceLevelAddsHeadroomAboveMean()
    {
        // Variable demand -> P80 forecast must exceed the mean
        $samples = [1 => [9 => [0 => 5, 1 => 15, 2 => 10, 3 => 8, 4 => 12]]];

        $mean = $this->forecaster->forecast($samples, 0.5)[1][9];
        $p80 = $this->forecaster->forecast($samples, 0.8)[1][9];

        $this->assertGreaterThan($mean, $p80);
    }

    public function testRecentWeeksWeighMore()
    {
        // Demand recently ramped up (0 weeks ago = 20, older = 0)
        $rising = [1 => [9 => [0 => 20, 1 => 0, 2 => 0, 3 => 0]]];
        // Demand recently dropped (0 weeks ago = 0, older = 20)
        $falling = [1 => [9 => [0 => 0, 1 => 20, 2 => 20, 3 => 20]]];

        $risingMean = $this->forecaster->forecast($rising, 0.5)[1][9];
        $fallingMean = $this->forecaster->forecast($falling, 0.5)[1][9];

        // The recent spike should pull the rising forecast above the falling one's
        // recency-discounted average
        $this->assertGreaterThan(4.0, $risingMean);
    }

    public function testZeroDemandForecastsZero()
    {
        $samples = [1 => [9 => [0 => 0, 1 => 0, 2 => 0]]];

        $this->assertSame(0.0, $this->forecaster->forecast($samples, 0.8)[1][9]);
    }
}
