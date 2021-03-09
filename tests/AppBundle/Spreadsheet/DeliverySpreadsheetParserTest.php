<?php

namespace Tests\AppBundle\Spreadsheet;

use AppBundle\Entity\Address;
use AppBundle\Service\Geocoder;
use AppBundle\Spreadsheet\AbstractSpreadsheetParser;
use AppBundle\Spreadsheet\DeliverySpreadsheetParser;
use Prophecy\Argument;

class DeliverySpreadsheetParserTest extends TestCase
{
    protected function createParser(): AbstractSpreadsheetParser
    {
        $this->geocoder = $this->prophesize(Geocoder::class);

        $this->geocoder
            ->geocode(Argument::type('string'))
            ->willReturn(new Address());

        return new DeliverySpreadsheetParser(
            $this->geocoder->reveal(),
        );
    }
}
