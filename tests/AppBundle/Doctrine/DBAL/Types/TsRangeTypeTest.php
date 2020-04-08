<?php

namespace Tests\AppBundle\Doctrine\DBAL\Types;

use AppBundle\DataType\TsRange;
use AppBundle\Doctrine\DBAL\Types\TsRangeType;
use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use PHPUnit\Framework\TestCase;

class TsRangeTypeTest extends TestCase
{
    public function convertToPHPValueProvider()
    {
        return [
            [
                '[2010-01-01 14:30, 2010-01-01 15:30)',
                new \DateTime('2010-01-01 14:30'),
                new \DateTime('2010-01-01 15:30')
            ],
            [
                '[2010-01-01 14:30,2010-01-01 15:30)',
                new \DateTime('2010-01-01 14:30'),
                new \DateTime('2010-01-01 15:30')
            ],
            [
                '[2010-01-01 14:30:00, 2010-01-01 15:30:00)',
                new \DateTime('2010-01-01 14:30:00'),
                new \DateTime('2010-01-01 15:30:00')
            ],
            [
                '[2010-01-01 14:30:00,2010-01-01 15:30:00)',
                new \DateTime('2010-01-01 14:30:00'),
                new \DateTime('2010-01-01 15:30:00')
            ],
            [
                '["2010-01-01 14:30:00", "2010-01-01 15:30:00"]',
                new \DateTime('2010-01-01 14:30:00'),
                new \DateTime('2010-01-01 15:30:00')
            ],
            [
                '["2010-01-01 14:30:00","2010-01-01 15:30:00"]',
                new \DateTime('2010-01-01 14:30:00'),
                new \DateTime('2010-01-01 15:30:00')
            ],
        ];
    }

    /**
     * @dataProvider convertToPHPValueProvider
     */
    public function testConvertToPHPValue($value, $expectedLower, $expectedUpper)
    {
        $platform = new PostgreSqlPlatform();

        $type = new TsRangeType();
        $phpValue = $type->convertToPHPValue($value, $platform);

        $this->assertInstanceOf(TsRange::class, $phpValue);

        $this->assertEquals($expectedLower, $phpValue->getLower());
        $this->assertEquals($expectedUpper, $phpValue->getUpper());
    }

    public function testConvertToDatabaseValue()
    {
        $platform = new PostgreSqlPlatform();

        $value = new TsRange();
        $value->setLower(new \DateTime('2010-01-01 14:30'));
        $value->setUpper(new \DateTime('2010-01-01 15:30'));

        $type = new TsRangeType();
        $databaseValue = $type->convertToDatabaseValue($value, $platform);

        $this->assertEquals('[2010-01-01 14:30,2010-01-01 15:30]', $databaseValue);
    }
}
