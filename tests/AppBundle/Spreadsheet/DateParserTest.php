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
                '2026-08-30', '2026-08-30'
            ],
            [
                '08-30', '2026-08-30'
            ],
            [
                '30/08/2026', '2026-08-30'
            ],
            [
                '30/08', '2026-08-30'
            ],
            [
                '30.08.2026', '2026-08-30'
            ],
            [
                '30.08', '2026-08-30'
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
