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
use Doctrine\ORM\EntityManager;
use Doctrine\Persistence\ObjectRepository;
use Exception;
use Prophecy\Argument;
use libphonenumber\PhoneNumberUtil;
use TypeError;

class DeliverySpreadsheetParserTest extends TestCase
{
    protected $settingManager;
    protected $geocoder;

    protected function createParser(): AbstractSpreadsheetParser
    {
        $this->entityManager = $this->prophesize(EntityManager::class);
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
            } else if ($args[0] === 'THIS ADDRESS WILL THROW') {
                throw new Exception();    
            } else {
                return $address;
            }
        });

        $geocoder->geocode(Argument::type('null'))->will(function ($args) use ($address) {
            throw new TypeError('Argument #1 ($text) must be of type string, null given');
        });

        $this->geocoder = $geocoder;

        $this->packageRepository = $this->prophesize(ObjectRepository::class);

        $this->entityManager->getRepository(Package::class)->willReturn($this->packageRepository->reveal());

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
        $delivery = array_shift($data)['delivery'];
        $this->assertEquals($delivery->getPickup()->getAddress()->getStreetAddress(), 'street address');
        $this->assertEquals($delivery->getDropoff()->getAddress()->getStreetAddress(), 'street address');

    }

    public function testWithInvalidPickupAddressWithCreateFlag()
    {
        $filename = realpath(__DIR__ . '/../Resources/spreadsheet/deliveries_invalid_address.csv');
        $parseResult = $this->parser->parse($filename, ['create_task_if_address_not_geocoded' => true]);
        $data = $parseResult->getData();

        /** @var Delivery */
        $delivery = array_shift($data)['delivery'];
        $this->assertEquals($delivery->getPickup()->getAddress()->getStreetAddress(), 'INVALID ADDRESS');
        $this->assertEquals($delivery->getPickup()->getAddress()->getGeo()->getLatitude(), 48.8534);
        $this->assertEquals($delivery->getPickup()->getAddress()->getGeo()->getLongitude(), 2.3488);
        $this->assertContains('review-needed', $delivery->getPickup()->getTags());
        $this->assertContains('my-task-tag', $delivery->getPickup()->getTags());
        $this->assertContains('my-other-task-tag', $delivery->getPickup()->getTags());


        $this->assertEquals('street address', $delivery->getDropoff()->getAddress()->getStreetAddress());
    }

    public function testWithAddressThatThrows()
    {
        $filename = realpath(__DIR__ . '/../Resources/spreadsheet/deliveries_with_address_that_throws.csv');
        $parseResult = $this->parser->parse($filename);
        $data = $parseResult->getErrors();

        $error = array_shift($data);

        $this->assertEquals('dropoff.address: Impossible de géocoder l\'adresse THIS ADDRESS WILL THROW', $error[0]);
    }

    public function testWithAddressThatThrowsAndCreateDeliveryAnyway()
    {
        $filename = realpath(__DIR__ . '/../Resources/spreadsheet/deliveries_with_address_that_throws.csv');
        $parseResult = $this->parser->parse($filename, ['create_task_if_address_not_geocoded' => true]);
                
        $data = $parseResult->getData();

        /** @var Delivery */
        $delivery = array_shift($data)['delivery'];
        
        $this->assertEquals($delivery->getDropoff()->getAddress()->getGeo()->getLatitude(), 48.8534);
        $this->assertEquals($delivery->getDropoff()->getAddress()->getGeo()->getLongitude(), 2.3488);
        
        $this->assertEquals($delivery->getPickup()->getAddress()->getGeo()->getLatitude(), 200);
        $this->assertEquals($delivery->getPickup()->getAddress()->getGeo()->getLongitude(), 200);
    
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
        $delivery = array_shift($data)['delivery'];
        $this->assertEquals($delivery->getPickup()->getMetadata()['foo'], 'fly');
        $this->assertEquals($delivery->getPickup()->getMetadata()['blu'], 'bla');
        $this->assertEquals($delivery->getDropoff()->getMetadata()['foo'], 'bar');
    }

    public function testWithExternalReference()
    {
        $filename = realpath(__DIR__ . '/../Resources/spreadsheet/deliveries_with_external_reference.csv');
        $parseResult = $this->parser->parse($filename);
        $data = $parseResult->getData();

        /** @var Delivery */
        $delivery = array_shift($data)['delivery'];
        $this->assertEquals('5392609', $delivery->getExternalReference());
        // Mirrored to the tasks, next to their own metadata
        $this->assertEquals('5392609', $delivery->getDropoff()->getMetadata()['external_reference']);
        $this->assertEquals('bar', $delivery->getDropoff()->getMetadata()['foo']);

        /** @var Delivery */
        $delivery = array_shift($data)['delivery'];
        $this->assertNull($delivery->getExternalReference());
    }

    /**
     * Every row used to fail on the missing column, one by one, after its addresses
     * were geocoded: a couple of seconds per row, hours on a large file.
     */
    public function testFileWithoutARequiredColumnFailsBeforeAnyRowIsRead()
    {
        $filename = realpath(__DIR__ . '/../Resources/spreadsheet/deliveries_missing_pickup_timeslot.csv');
        $parseResult = $this->parser->parse($filename);

        $this->assertEmpty($parseResult->getData());
        $this->assertEquals([
            ['row' => 0, 'errors' => ["Le fichier n'a pas de colonne pickup.timeslot, ajoutez-la et importez à nouveau le fichier"]]
        ], $parseResult->getNormalizedErrors());

        $this->geocoder->geocode(Argument::any())->shouldNotHaveBeenCalled();
    }

    /**
     * A row with an empty time slot or address used to be found out only after
     * its addresses were geocoded, a couple of seconds per row
     */
    public function testRowsWithEmptyMandatoryValuesAreNotGeocoded()
    {
        $filename = realpath(__DIR__ . '/../Resources/spreadsheet/deliveries_with_empty_values.csv');

        $handledRows = [];
        $parseResult = $this->parser->parse($filename, [
            'on_row' => function (int $processedRows) use (&$handledRows) {
                $handledRows[] = $processedRows;
            }
        ]);

        $this->assertEquals([1], array_keys($parseResult->getData()));
        $this->assertEquals([
            2 => ['La plage horaire "" dans pickup.timeslot n\'est pas valide'],
            3 => ['dropoff.address: Impossible de géocoder l\'adresse dropoff'],
        ], $parseResult->getErrors());

        $this->geocoder->geocode('ROW 2 PICKUP')->shouldNotHaveBeenCalled();
        $this->geocoder->geocode('ROW 2 DROPOFF')->shouldNotHaveBeenCalled();
        $this->geocoder->geocode('ROW 3 PICKUP')->shouldNotHaveBeenCalled();

        // The rows skipped count in the progress all the same
        $this->assertEquals([1, 2, 3], $handledRows);
    }

    public function testProgressIsReportedAfterEachRow()
    {
        $filename = realpath(__DIR__ . '/../Resources/spreadsheet/deliveries_invalid_address.csv');

        $calls = [];
        $this->parser->parse($filename, [
            'on_row' => function (int $processedRows, int $totalRows, int $errorRows) use (&$calls) {
                $calls[] = [$processedRows, $totalRows, $errorRows];
            }
        ]);

        // One row, which fails on its invalid pickup address
        $this->assertEquals([[1, 1, 1]], $calls);
    }

    public function testWhatTheProgressCallbackThrowsStopsTheParsing()
    {
        $filename = realpath(__DIR__ . '/../Resources/spreadsheet/deliveries_with_external_reference.csv');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stop');

        $this->parser->parse($filename, [
            'on_row' => function () {
                throw new \RuntimeException('Stop');
            }
        ]);
    }

    public function testWithTour()
    {
        $filename = realpath(__DIR__ . '/../Resources/spreadsheet/deliveries_with_tour.csv');
        
        $parseResult = $this->parser->parse($filename);
        $data = $parseResult->getData();

        /** @var Delivery */
        $result = array_shift($data);
        $this->assertEquals($result['tourName'], 'test route');
    }
}
