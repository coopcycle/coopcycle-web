<?php

namespace Tests\AppBundle\Doctrine\DBAL\Types;

use AppBundle\DataType\NumRange;
use AppBundle\Doctrine\DBAL\Types\NumRangeType;
use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use PHPUnit\Framework\TestCase;

class NumRangeTypeTest extends TestCase
{
    public function convertToPHPValueProvider()
    {
        return [
            [
                '[0,)',
                0, INF, true
            ],
            [
                '[0,1]',
                0, 1, false
            ],
            [
                '[1,1]',
                1, 1, false
            ],
            [
                '[0,10]',
                0, 10, false
            ],
            [
                '[10,15]',
                10, 15, false
            ],
            [
                '[10,)',
                10, INF, true
            ]
        ];
    }

    /**
     * @dataProvider convertToPHPValueProvider
     */
    public function testConvertToPHPValue($value, $expectedLower, $expectedUpper, $expectedIsUpperInfinite)
    {
        $type = new NumRangeType();
        $phpValue = $type->convertToPHPValue($value, new PostgreSqlPlatform());

        $this->assertInstanceOf(NumRange::class, $phpValue);

        $this->assertEquals($expectedLower, $phpValue->getLower());
        $this->assertEquals($expectedUpper, $phpValue->getUpper());
        $this->assertEquals($expectedIsUpperInfinite, $phpValue->isUpperInfinite());
    }
}
