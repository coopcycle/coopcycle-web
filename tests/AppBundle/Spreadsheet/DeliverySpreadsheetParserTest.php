<?php

namespace Tests\AppBundle\Spreadsheet;

use AppBundle\Entity\Address;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Package;
use AppBundle\Service\Geocoder;
use AppBundle\Spreadsheet\AbstractSpreadsheetParser;
use AppBundle\Spreadsheet\DeliverySpreadsheetParser;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Prophecy\Argument;
use libphonenumber\PhoneNumberUtil;
use Symfony\Contracts\Translation\TranslatorInterface;

class DeliverySpreadsheetParserTest extends TestCase
{
    protected function createParser(): AbstractSpreadsheetParser
    {
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);
        $this->slugify = $this->prophesize(SlugifyInterface::class);
        $this->translator = $this->prophesize(TranslatorInterface::class);

        $this->slugify->slugify(Argument::type('string'))->willReturn('');
        
        $address = new Address();
        $address->setGeo(new GeoCoordinates(200, 200));
        $address->setStreetAddress('street address');

        $geocoder = $this->prophesize(Geocoder::class);
        $geocoder->geocode(Argument::type('string'))->will(function ($args) use ($address) {
            
            if ($args[0] === 'THIS ADDRESS IS INVALID') {
                return null;
            } else {
                return $address;
            }
        });

        $this->geocoder = $geocoder;

        $this->packageRepository = $this->prophesize(ObjectRepository::class);

        $this->entityManager
            ->getRepository(Package::class)
            ->willReturn($this->packageRepository->reveal());

        return new DeliverySpreadsheetParser(
            $this->geocoder->reveal(),
            PhoneNumberUtil::getInstance(),
            'fr',
            $this->entityManager->reveal(),
            $this->slugify->reveal(),
            $this->translator->reveal()
        );
    }

    public function testCsvWithEmptyLines()
    {

        $filename = realpath(__DIR__ . '/../Resources/spreadsheet/deliveries_empty_lines.csv');
        $parseResult = $this->parser->parse($filename);
        $data = $parseResult->getData();
        $this->geocoder->geocode(null)->shouldNotBeCalled(); // not called with empty lines

        /** @var Delivery */
        $delivery = array_shift($data);
        $this->assertEquals($delivery->getPickup()->getAddress()->getStreetAddress(), 'street address');
        $this->assertEquals($delivery->getDropoff()->getAddress()->getStreetAddress(), 'street address');

    }

    public function testWithInvalidPickupAddress()
    {
        $filename = realpath(__DIR__ . '/../Resources/spreadsheet/deliveries_invalid_address.csv');
        $parseResult = $this->parser->parse($filename, ['create_task_if_address_not_geocoded' => true]);
        $data = $parseResult->getData();

        /** @var Delivery */
        $delivery = array_shift($data);
        $this->assertEquals($delivery->getPickup()->getAddress()->getStreetAddress(), 'INVALID ADDRESS');

    }

    public function testWithMetadata()
    {
        $filename = realpath(__DIR__ . '/../Resources/spreadsheet/deliveries_with_metadata.csv');
        $parseResult = $this->parser->parse($filename);
        $data = $parseResult->getData();

        /** @var Delivery */
        $delivery = array_shift($data);
        $this->assertEquals($delivery->getPickup()->getMetadata()['foo'], 'fly');
        $this->assertEquals($delivery->getDropoff()->getMetadata()['foo'], 'bar');

    }
}
