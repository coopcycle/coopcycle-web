<?php

namespace Tests\AppBundle\Spreadsheet;

use AppBundle\Spreadsheet\DateParser;
use PHPUnit\Framework\TestCase;

class DateParserTest extends TestCase
{
    public function parseDateProvider()
    {
        return [
            [
                '2025-08-30', '2025-08-30'
            ],
            [
                '08-30', '2025-08-30'
            ],
            [
                '30/08/2025', '2025-08-30'
            ],
            [
                '30/08', '2025-08-30'
            ],
            [
                '30.08.2025', '2025-08-30'
            ],
            [
                '30.08', '2025-08-30'
            ],
        ];
    }

    /**
     * @dataProvider parseDateProvider
     */
    public function testParseDate($text, $expected)
    {
        $date = new \DateTime();

        DateParser::parseDate($date, $text);

        $this->assertEquals($expected, $date->format('Y-m-d'));
    }
}
