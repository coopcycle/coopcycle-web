<?php

namespace Tests\AppBundle\Spreadsheet;

use AppBundle\Entity\User;
use AppBundle\Entity\Address;
use AppBundle\Entity\Tag;
use AppBundle\Service\Geocoder;
use AppBundle\Service\TagManager;
use AppBundle\Spreadsheet\DeliverySpreadsheetParser;
use Cocur\Slugify\Slugify;
use FOS\UserBundle\Model\UserManagerInterface;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberUtil;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

class DeliverySpreadsheetParserTest extends TestCase
{
    use ProphecyTrait;

    private $geocoder;
    private $tagManager;

    private $parser;

    public function setUp(): void
    {
        $this->geocoder = $this->prophesize(Geocoder::class);
        $this->tagManager = $this->prophesize(TagManager::class);
        $this->phoneNumberUtil = $this->prophesize(PhoneNumberUtil::class);

        $this->parser = new DeliverySpreadsheetParser(
            $this->geocoder->reveal(),
            $this->tagManager->reveal(),
            new Slugify(),
            $this->phoneNumberUtil->reveal(),
            'fr'
        );
    }

    private function mockDependencies()
    {
        $this->geocoder
            ->geocode(Argument::type('string'))
            ->willReturn(new Address());

        $this->tagManager
            ->fromSlugs(Argument::type('array'))
            ->willReturn([ new Tag() ]);

        $this->phoneNumberUtil
            ->parse(Argument::any(), Argument::type('string'))
            ->willReturn(new PhoneNumber());
    }

    public function testCanParseExampleData()
    {
        $this->mockDependencies();

        $deliveries = $this->parser->parseData($this->parser->getExampleData());

        $this->assertCount(2, $deliveries);
    }
}
