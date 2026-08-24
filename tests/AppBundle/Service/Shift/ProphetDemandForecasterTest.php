<?php

namespace Tests\AppBundle\Service\Shift;

use AppBundle\Service\Shift\HeuristicDemandForecaster;
use AppBundle\Service\Shift\ProphetDemandForecaster;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class ProphetDemandForecasterTest extends TestCase
{
    private \DateTimeImmutable $windowEnd;

    public function setUp(): void
    {
        // A Monday entirely in the past, so no sample point is dropped as "future"
        $this->windowEnd = (new \DateTimeImmutable('monday this week'))->setTime(0, 0);
    }

    private function createForecaster(MockHttpClient $client): ProphetDemandForecaster
    {
        return new ProphetDemandForecaster(
            $client,
            new HeuristicDemandForecaster(),
            new NullLogger(),
            'fr'
        );
    }

    /**
     * A zero-filled grid (like TaskRepository builds) for dows 1-2, hours 9-10,
     * with constant demand of 10 on every bucket of every week.
     */
    private function samples(int $weeks = 6): array
    {
        $samples = [];
        for ($dow = 1; $dow <= 2; $dow++) {
            for ($hour = 9; $hour <= 10; $hour++) {
                $samples[$dow][$hour] = array_fill(0, $weeks, 10);
            }
        }

        return $samples;
    }

    public function testMapsPredictionsBackToDowHourBuckets()
    {
        $capturedBody = null;

        $client = new MockHttpClient(function ($method, $url, $options) use (&$capturedBody) {
            $capturedBody = json_decode($options['body'], true);

            $predictions = [];
            foreach ($capturedBody['horizon'] as $i => $ds) {
                $predictions[] = ['ds' => $ds, 'yhat' => 10.0 + $i];
            }

            return new MockResponse(json_encode(['predictions' => $predictions]));
        }, 'http://recommender:8000');

        $forecaster = $this->createForecaster($client);
        $forecast = $forecaster->forecast($this->samples(), 0.8, $this->windowEnd, 'Europe/Paris');

        $this->assertSame('prophet', $forecaster->getLastSource());

        // 2 dows x 2 hours forecast, horizon sorted chronologically:
        // Mon 9h, Mon 10h, Tue 9h, Tue 10h
        $this->assertSame(10.0, $forecast[1][9]);
        $this->assertSame(11.0, $forecast[1][10]);
        $this->assertSame(12.0, $forecast[2][9]);
        $this->assertSame(13.0, $forecast[2][10]);

        $this->assertSame(0.8, $capturedBody['quantile']);
        $this->assertSame('fr', $capturedBody['country_holidays']);
    }

    public function testSeriesReconstructsRealDatesFromWeeksAgoBuckets()
    {
        $capturedBody = null;

        $client = new MockHttpClient(function ($method, $url, $options) use (&$capturedBody) {
            $capturedBody = json_decode($options['body'], true);

            return new MockResponse(json_encode(['predictions' => []]));
        }, 'http://recommender:8000');

        $this->createForecaster($client)
            ->forecast($this->samples(6), 0.8, $this->windowEnd, 'Europe/Paris');

        // 2 dows x 2 hours x 6 weeks
        $this->assertCount(24, $capturedBody['series']);

        // weeksAgo=0, dow=1, hour=9 -> the Monday of the week before windowEnd
        $expected = $this->windowEnd->modify('-7 days')->setTime(9, 0)->format('Y-m-d H:i:s');
        $dates = array_column($capturedBody['series'], 'ds');
        $this->assertContains($expected, $dates);

        // Oldest point: weeksAgo=5 -> 6 weeks before windowEnd
        $oldest = $this->windowEnd->modify('-42 days')->setTime(9, 0)->format('Y-m-d H:i:s');
        $this->assertSame($oldest, $dates[0]);
    }

    public function testTrimsAllZeroPrefix()
    {
        $capturedBody = null;

        $client = new MockHttpClient(function ($method, $url, $options) use (&$capturedBody) {
            $capturedBody = json_decode($options['body'], true);

            return new MockResponse(json_encode(['predictions' => []]));
        }, 'http://recommender:8000');

        // 10 weeks of buckets, but the oldest 4 weeks predate the instance (all zeros)
        $samples = [];
        for ($dow = 1; $dow <= 2; $dow++) {
            for ($hour = 9; $hour <= 10; $hour++) {
                $samples[$dow][$hour] = array_merge(array_fill(0, 6, 10), array_fill(6, 4, 0));
            }
        }

        $this->createForecaster($client)
            ->forecast($samples, 0.8, $this->windowEnd, 'Europe/Paris');

        // The 16 all-zero pre-launch points are gone, the 24 active ones remain
        $this->assertCount(24, $capturedBody['series']);
        $this->assertSame(10, $capturedBody['series'][0]['y']);
    }

    public function testFallsBackToHeuristicWhenServiceFails()
    {
        $client = new MockHttpClient(
            new MockResponse('oops', ['http_code' => 500]),
            'http://recommender:8000'
        );

        $forecaster = $this->createForecaster($client);
        $forecast = $forecaster->forecast($this->samples(), 0.5, $this->windowEnd, 'Europe/Paris');

        $this->assertSame('heuristic', $forecaster->getLastSource());
        // Constant demand of 10 -> the heuristic median is 10
        $this->assertEqualsWithDelta(10.0, $forecast[1][9], 1e-6);
    }

    public function testFallsBackToHeuristicWithoutWindowContext()
    {
        $client = new MockHttpClient([], 'http://recommender:8000');

        $forecaster = $this->createForecaster($client);
        $forecast = $forecaster->forecast($this->samples(), 0.5);

        $this->assertSame('heuristic', $forecaster->getLastSource());
        $this->assertEqualsWithDelta(10.0, $forecast[1][9], 1e-6);
    }

    public function testFallsBackToHeuristicOnThinHistory()
    {
        $client = new MockHttpClient([], 'http://recommender:8000');

        $forecaster = $this->createForecaster($client);
        // 2 weeks of history is under the 28-day minimum for seasonal models
        $forecast = $forecaster->forecast($this->samples(2), 0.5, $this->windowEnd, 'Europe/Paris');

        $this->assertSame('heuristic', $forecaster->getLastSource());
        $this->assertEqualsWithDelta(10.0, $forecast[1][9], 1e-6);
    }
}
