<?php

namespace Tests\AppBundle\Entity\Task;

use AppBundle\Entity\Task\RecurrenceRule;
use PHPUnit\Framework\TestCase;

class RecurrenceRuleTest extends TestCase
{
    public function isActiveOnProvider()
    {
        return [
            'no bounds' => [null, null, '2024-01-15', true],
            'before the start date' => ['2024-01-15', null, '2024-01-14', false],
            'on the start date' => ['2024-01-15', null, '2024-01-15', true],
            'after the start date' => ['2024-01-15', null, '2024-01-16', true],
            'before the end date' => [null, '2024-06-30', '2024-06-29', true],
            'on the end date' => [null, '2024-06-30', '2024-06-30', true],
            'after the end date' => [null, '2024-06-30', '2024-07-01', false],
            'inside both bounds' => ['2024-01-15', '2024-06-30', '2024-03-01', true],
            'outside both bounds' => ['2024-01-15', '2024-06-30', '2023-12-31', false],
            // The time of day is irrelevant, the bounds are plain days
            'late on the end date' => [null, '2024-06-30', '2024-06-30 23:30', true],
        ];
    }

    /**
     * @dataProvider isActiveOnProvider
     */
    public function testIsActiveOn(?string $startDate, ?string $endDate, string $date, bool $expected)
    {
        $recurrenceRule = new RecurrenceRule();
        $recurrenceRule->setStartDate($startDate ? new \DateTime($startDate) : null);
        $recurrenceRule->setEndDate($endDate ? new \DateTime($endDate) : null);

        $this->assertSame($expected, $recurrenceRule->isActiveOn(new \DateTime($date)));
    }
}
