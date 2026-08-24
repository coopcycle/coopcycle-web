<?php

namespace Tests\AppBundle\Service\Shift;

use AppBundle\Service\Shift\ShiftBuilder;
use PHPUnit\Framework\TestCase;

class ShiftBuilderTest extends TestCase
{
    private ShiftBuilder $builder;

    public function setUp(): void
    {
        $this->builder = new ShiftBuilder();
    }

    private function coverage(array $blocks, int $openHour, int $closeHour): array
    {
        $coverage = array_fill($openHour, $closeHour - $openHour, 0);
        foreach ($blocks as $block) {
            for ($h = $block['start']; $h < $block['end']; $h++) {
                $coverage[$h] += $block['slots'];
            }
        }

        return $coverage;
    }

    public function testStaircaseDecompositionProducesStaggeredShifts()
    {
        // need: 09-10:1, 11-14:ramps to 3, 15-17:1
        $need = [
            9 => 1, 10 => 1, 11 => 2, 12 => 3, 13 => 3, 14 => 2, 15 => 1, 16 => 1, 17 => 1,
        ];

        $blocks = $this->builder->buildDay($need, 8, 22, 1, 12);

        // Three layers: the whole 09-18 baseline, the 11-15 second courier, the 12-14 third
        $this->assertEqualsCanonicalizing([
            ['start' => 9, 'end' => 18, 'slots' => 1],
            ['start' => 11, 'end' => 15, 'slots' => 1],
            ['start' => 12, 'end' => 14, 'slots' => 1],
        ], $blocks);

        // Coverage must meet demand at every hour
        $coverage = $this->coverage($blocks, 9, 18);
        foreach ($need as $hour => $required) {
            $this->assertGreaterThanOrEqual($required, $coverage[$hour]);
        }
    }

    public function testEmptyDemandProducesNoShifts()
    {
        $need = array_fill(8, 14, 0);

        $this->assertSame([], $this->builder->buildDay($need, 8, 22, 3, 8));
    }

    public function testMinLengthExtendsShortPeak()
    {
        // A single 1-hour spike at 13:00
        $need = [13 => 1];

        $blocks = $this->builder->buildDay($need, 8, 22, 3, 8);

        $this->assertCount(1, $blocks);
        $this->assertSame(3, $blocks[0]['end'] - $blocks[0]['start']);
        // Must still cover the demanded hour
        $this->assertLessThanOrEqual(13, $blocks[0]['start']);
        $this->assertGreaterThan(13, $blocks[0]['end']);
    }

    public function testMaxLengthSplitsLongShift()
    {
        // 12 straight hours of demand, max shift 8h
        $need = array_fill(8, 12, 1);

        $blocks = $this->builder->buildDay($need, 8, 20, 3, 8);

        $this->assertGreaterThanOrEqual(2, count($blocks));
        foreach ($blocks as $block) {
            $this->assertLessThanOrEqual(8, $block['end'] - $block['start']);
        }
        // Full coverage preserved
        $coverage = $this->coverage($blocks, 8, 20);
        foreach ($coverage as $c) {
            $this->assertGreaterThanOrEqual(1, $c);
        }
    }

    public function testIdenticalIntervalsMergeIntoSlots()
    {
        // Flat demand of 3 couriers across the whole window
        $need = array_fill(10, 4, 3);

        $blocks = $this->builder->buildDay($need, 10, 14, 1, 12);

        $this->assertSame([['start' => 10, 'end' => 14, 'slots' => 3]], $blocks);
    }
}
