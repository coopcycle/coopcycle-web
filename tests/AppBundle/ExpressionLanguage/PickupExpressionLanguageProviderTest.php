<?php

namespace Tests\AppBundle\ExpressionLanguage;

use AppBundle\Entity\Task;
use AppBundle\ExpressionLanguage\PickupExpressionLanguageProvider;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class PickupExpressionLanguageProviderTest extends TestCase
{
    private $language;

    public function setUp(): void
    {
        $this->language = new ExpressionLanguage();
    }

    public function tearDown(): void
    {
        Carbon::setTestNow();
    }

    public function returnValueProvider()
    {
        return [
            [ '2019-08-19 09:00:00', '2019-08-20 12:00:00', 1 ],
            [ '2019-08-19 09:00:00', '2019-08-21 12:00:00', 2 ],
            [ '2019-08-19 09:00:00', '2019-08-22 12:00:00', 3 ],
            [ '2019-08-19 23:30:00', '2019-08-20 23:59:59', 1 ],
            [ '2019-08-19 23:59:59', '2019-08-20 23:59:58', 1 ],
            [ '2019-08-19 09:00:00', '2019-08-19 16:00:00', 0 ],
        ];
    }

    /**
     * @dataProvider returnValueProvider
     */
    public function testReturnValue($now, $before, $expectedValue)
    {
        Carbon::setTestNow(Carbon::parse($now));

        $this->language->registerProvider(new PickupExpressionLanguageProvider());

        $pickup = new \stdClass();
        $pickup->before = new \DateTime($before);

        $value = $this->language->evaluate('diff_days(pickup)', [
            'pickup' => $pickup,
        ]);

        $this->assertThat($value, $this->isType('int'));
        $this->assertEquals($expectedValue, $value);
    }

    public function withOperatorProvider()
    {
        return [
            [ '2019-08-19 09:00:00', '2019-08-20 12:00:00', 'diff_days(pickup) == 1', true ],
            [ '2019-08-19 09:00:00', '2019-08-21 12:00:00', 'diff_days(pickup) > 1', true ],
            [ '2019-08-19 09:00:00', '2019-08-22 12:00:00', 'diff_days(pickup) >= 3', true ],
            [ '2019-08-19 23:59:59', '2019-08-20 12:00:00', 'diff_days(pickup) in 0..1', true ],
            [ '2019-08-19 09:00:00', '2019-08-19 16:00:00', 'diff_days(pickup) > 0', false ],
        ];
    }

    /**
     * @dataProvider withOperatorProvider
     */
    public function testWithOperator($now, $before, $expression, $expectedValue)
    {
        Carbon::setTestNow(Carbon::parse($now));

        $this->language->registerProvider(new PickupExpressionLanguageProvider());

        $pickup = new \stdClass();
        $pickup->before = new \DateTime($before);

        $value = $this->language->evaluate($expression, [
            'pickup' => $pickup,
        ]);

        $this->assertThat($value, $this->isType('boolean'));
        $this->assertEquals($expectedValue, $value);
    }

    public function diffHoursProvider()
    {
        return [
            [ '2019-08-19 09:00:00', '2019-08-19 12:00:00', 3 ],
            [ '2019-08-19 09:00:00', '2019-08-19 10:30:00', 1.5 ],
            [ '2019-08-19 09:00:00', '2019-08-19 14:30:00', 5.5 ],
            [ '2019-08-19 09:00:00', '2019-08-19 11:30:00', 2.5 ],
        ];
    }

    /**
     * @dataProvider diffHoursProvider
     */
    public function testDiffHoursReturnValue($now, $before, $expectedValue)
    {
        Carbon::setTestNow(Carbon::parse($now));

        $this->language->registerProvider(new PickupExpressionLanguageProvider());

        $pickup = new \stdClass();
        $pickup->before = new \DateTime($before);

        $value = $this->language->evaluate('diff_hours(pickup)', [
            'pickup' => $pickup,
        ]);

        $this->assertEquals($expectedValue, $value);
    }

    public function diffDaysWithOperatorProvider()
    {
        return [
            [ '2019-08-19 09:00:00', '2019-08-19 12:00:00', 'diff_hours(pickup) == 3', true ],
            [ '2019-08-19 09:00:00', '2019-08-19 12:00:00', 'diff_hours(pickup) >= 3', true ],
            [ '2019-08-19 09:00:00', '2019-08-19 10:30:00', 'diff_hours(pickup) > 1',  true ],
        ];
    }

    /**
     * @dataProvider diffDaysWithOperatorProvider
     */
    public function testDiffDaysWithOperator($now, $before, $expression, $expectedValue)
    {
        Carbon::setTestNow(Carbon::parse($now));

        $this->language->registerProvider(new PickupExpressionLanguageProvider());

        $pickup = new \stdClass();
        $pickup->before = new \DateTime($before);

        $value = $this->language->evaluate($expression, [
            'pickup' => $pickup,
        ]);

        $this->assertThat($value, $this->isType('boolean'));
        $this->assertEquals($expectedValue, $value);
    }

    public function existingTaskProvider()
    {
        return [
            [ '2019-08-19 09:00:00', '2019-08-03 12:00:00', '2019-08-01 09:00:00', 2 ],
            [ '2019-08-19 09:00:00', '2019-08-03 12:00:00', '2019-08-02 09:00:00', 1 ],
        ];
    }

    /**
     * @dataProvider existingTaskProvider
     */
    public function testExistingTask($now, $before, $createdAt, $expectedValue)
    {
        Carbon::setTestNow(Carbon::parse($now));

        $this->language->registerProvider(new PickupExpressionLanguageProvider());

        $pickup = new \stdClass();
        $pickup->before = new \DateTime($before);
        $pickup->createdAt = new \DateTime($createdAt);

        $value = $this->language->evaluate('diff_days(pickup)', [
            'pickup' => $pickup,
        ]);

        $this->assertThat($value, $this->isType('int'));
        $this->assertEquals($expectedValue, $value);
    }

    public function timeRangeLengthProvider()
    {
        return [
            [ '2019-08-19 09:00:00', '2019-08-19 12:00:00', 3.0 ],
            [ '2019-08-19 09:00:00', '2019-08-19 10:00:00', 1.0 ],
            [ '2019-08-19 09:00:00', '2019-08-19 09:30:00', 0.5 ],
            [ '2019-08-19 09:00:00', '2019-08-20 09:30:00', 24.5 ],
        ];
    }

    /**
     * @dataProvider timeRangeLengthProvider
     */
    public function testTimeRangeLength($after, $before, $expectedValue)
    {
        $this->language->registerProvider(new PickupExpressionLanguageProvider());

        $pickup = new \stdClass();
        $pickup->after = new \DateTime($after);
        $pickup->before = new \DateTime($before);

        $value = $this->language->evaluate('time_range_length(pickup, "hours")', [
            'pickup' => $pickup,
        ]);

        $this->assertIsNumeric($value);
        $this->assertEquals($expectedValue, $value);
    }

    public function timeRangeLengthProviderIn()
    {
        return [
            [ '2019-08-19 09:00:00', '2019-08-19 12:00:00', true ],
            [ '2019-08-19 09:00:00', '2019-08-19 10:00:00', false ],
            [ '2019-08-19 09:00:00', '2019-08-19 09:30:00', false ],
            [ '2019-08-19 09:00:00', '2019-08-20 09:30:00', false ],
        ];
    }

    /**
     * @dataProvider timeRangeLengthProviderIn
     */
    public function testTimeRangeLengthIn($after, $before, $expectedValue)
    {
        $this->language->registerProvider(new PickupExpressionLanguageProvider());

        $dropoff = new \stdClass();
        $dropoff->after = new \DateTime($after);
        $dropoff->before = new \DateTime($before);

        $value = $this->language->evaluate('time_range_length(dropoff, "hours") in 2..8', [
            'dropoff' => $dropoff,
        ]);

        $this->assertSame($expectedValue, $value);
    }
}
