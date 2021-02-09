<?php

namespace Tests\AppBundle\Spreadsheet;

use AppBundle\Entity\Address;
use AppBundle\Entity\Tag;
use AppBundle\Service\Geocoder;
use AppBundle\Service\TagManager;
use AppBundle\Spreadsheet\AbstractSpreadsheetParser;
use AppBundle\Spreadsheet\DeliverySpreadsheetParser;
use Cocur\Slugify\Slugify;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberUtil;
use Prophecy\Argument;

class DeliverySpreadsheetParserTest extends TestCase
{
    protected function createParser(): AbstractSpreadsheetParser
    {
        $this->geocoder = $this->prophesize(Geocoder::class);
        $this->tagManager = $this->prophesize(TagManager::class);
        $this->phoneNumberUtil = $this->prophesize(PhoneNumberUtil::class);

        $this->geocoder
            ->geocode(Argument::type('string'))
            ->willReturn(new Address());

        $this->tagManager
            ->fromSlugs(Argument::type('array'))
            ->willReturn([ new Tag() ]);

        $this->phoneNumberUtil
            ->parse(Argument::any(), Argument::type('string'))
            ->willReturn(new PhoneNumber());

        return new DeliverySpreadsheetParser(
            $this->geocoder->reveal(),
            $this->tagManager->reveal(),
            new Slugify(),
            $this->phoneNumberUtil->reveal(),
            'fr'
        );
    }
}
