<?php

namespace Tests\AppBundle\Spreadsheet;

use AppBundle\Entity\Address;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Package;
use AppBundle\Service\Geocoder;
use AppBundle\Service\SettingsManager;
use AppBundle\Spreadsheet\AbstractSpreadsheetParser;
use AppBundle\Spreadsheet\DeliverySpreadsheetParser;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Prophecy\Argument;
use libphonenumber\PhoneNumberUtil;
use Symfony\Contracts\Translation\TranslatorInterface;
use TypeError;

class DeliverySpreadsheetParserTest extends TestCase
{
    protected function createParser(): AbstractSpreadsheetParser
    {
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);
        $this->slugify = $this->prophesize(SlugifyInterface::class);
        $this->settingManager = $this->prophesize(SettingsManager::class);

        $this->settingManager
            ->get('latlng')
            ->willReturn('48.8534,2.3488');

        $this->slugify->slugify(Argument::type('string'))->will(function ($args){return $args[0];});

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

        $geocoder->geocode(Argument::type('null'))->will(function ($args) use ($address) {
            throw new TypeError('Argument #1 ($text) must be of type string, null given');
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
            $this->translator,
            $this->settingManager->reveal()
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

    public function testWithInvalidPickupAddressWithCreateFlag()
    {
        $filename = realpath(__DIR__ . '/../Resources/spreadsheet/deliveries_invalid_address.csv');
        $parseResult = $this->parser->parse($filename, ['create_task_if_address_not_geocoded' => true]);
        $data = $parseResult->getData();

        /** @var Delivery */
        $delivery = array_shift($data);
        $this->assertEquals($delivery->getPickup()->getAddress()->getStreetAddress(), 'INVALID ADDRESS');
        $this->assertContains('review-needed', $delivery->getPickup()->getTags());
        $this->assertContains('my-task-tag', $delivery->getPickup()->getTags());
        $this->assertContains('my-other-task-tag', $delivery->getPickup()->getTags());
    }

    public function testWithEmptyPickupAndDropoffAddresses()
    {
        $filename = realpath(__DIR__ . '/../Resources/spreadsheet/delivery_empty_dropoff_address.csv');
        $parseResult = $this->parser->parse($filename);
        $data = $parseResult->getErrors();

        $error = array_shift($data);

        $this->assertEquals($error[0], 'pickup.address: Impossible de géocoder l\'adresse pickup');
        $this->assertEquals($error[1], 'dropoff.address: Impossible de géocoder l\'adresse dropoff');
    }

    public function testWithMetadata()
    {
        $filename = realpath(__DIR__ . '/../Resources/spreadsheet/deliveries_with_metadata.csv');
        $parseResult = $this->parser->parse($filename);
        $data = $parseResult->getData();

        /** @var Delivery */
        $delivery = array_shift($data);
        $this->assertEquals($delivery->getPickup()->getMetadata()['foo'], 'fly');
        $this->assertEquals($delivery->getPickup()->getMetadata()['blu'], 'bla');
        $this->assertEquals($delivery->getDropoff()->getMetadata()['foo'], 'bar');
    }
}
