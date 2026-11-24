<?php

namespace Tests\AppBundle\Spreadsheet\Column;

use AppBundle\Spreadsheet\Column\ColumnDefinition;
use AppBundle\Spreadsheet\Column\ColumnDetector;
use AppBundle\Spreadsheet\Column\ColumnGroup;
use AppBundle\Spreadsheet\Column\ColumnType;
use AppBundle\Spreadsheet\SpreadsheetPreview;
use Cocur\Slugify\Slugify;
use libphonenumber\PhoneNumberUtil;
use PHPUnit\Framework\TestCase;

class ColumnDetectorTest extends TestCase
{
    private ColumnDetector $detector;

    public function setUp(): void
    {
        $this->detector = new ColumnDetector(
            new Slugify(),
            PhoneNumberUtil::getInstance(),
            'fr'
        );
    }

    /**
     * A subset of the delivery columns, enough to cover the cases that matter
     *
     * @return ColumnDefinition[]
     */
    private function definitions(): array
    {
        return [
            new ColumnDefinition('pickup.address', ColumnType::Address, ColumnGroup::Pickup, true, [
                'adresse enlevement',
            ]),
            new ColumnDefinition('pickup.timeslot', ColumnType::Timeslot, ColumnGroup::Pickup, true, [
                'creneau enlevement',
            ]),
            new ColumnDefinition('pickup.address.telephone', ColumnType::Phone, ColumnGroup::Pickup, false, [
                'telephone enlevement',
            ]),
            new ColumnDefinition('dropoff.address', ColumnType::Address, ColumnGroup::Dropoff, true, [
                'adresse livraison',
            ]),
            new ColumnDefinition('dropoff.timeslot', ColumnType::Timeslot, ColumnGroup::Dropoff, true, [
                'creneau livraison',
            ]),
            new ColumnDefinition('dropoff.address.telephone', ColumnType::Phone, ColumnGroup::Dropoff, false, [
                'telephone livraison',
            ]),
            new ColumnDefinition('dropoff.packages', ColumnType::Packages, ColumnGroup::Dropoff, false, [
                'colis',
            ]),
            new ColumnDefinition('weight', ColumnType::Number, ColumnGroup::Delivery, false, ['poids']),
        ];
    }

    private function detect(array $header, array $rows = []): array
    {
        return $this->detector
            ->detect(new SpreadsheetPreview($header, $rows), $this->definitions())
            ->toArray();
    }

    /**
     * A file exported from here, or built from the example, maps onto itself
     */
    public function testOurOwnColumnNames()
    {
        $header = ['pickup.address', 'pickup.timeslot', 'dropoff.address', 'dropoff.timeslot', 'weight'];

        $this->assertSame([
            0 => 'pickup.address',
            1 => 'pickup.timeslot',
            2 => 'dropoff.address',
            3 => 'dropoff.timeslot',
            4 => 'weight',
        ], $this->detect($header));
    }

    public function testAccentedAndPunctuatedNames()
    {
        $header = ['Adresse enlèvement', 'CRÉNEAU_ENLEVEMENT', 'Adresse livraison', 'Créneau livraison', 'Poids'];

        $this->assertSame([
            0 => 'pickup.address',
            1 => 'pickup.timeslot',
            2 => 'dropoff.address',
            3 => 'dropoff.timeslot',
            4 => 'weight',
        ], $this->detect($header));
    }

    public function testUnknownColumnsAreLeftToTheUser()
    {
        $mapping = $this->detect(['Numéro de dossier', 'Chauffeur', '']);

        $this->assertSame([], $mapping);
    }

    public function testAFieldIsNeverMappedTwice()
    {
        // Two columns both named like the pickup address: only the first can be it
        $mapping = $this->detect(['Adresse enlèvement', 'adresse_enlevement']);

        $this->assertSame([0 => 'pickup.address'], $mapping);
    }

    /**
     * The heart of the matter: with both time slot columns free, nothing says which of the
     * two an unnamed column of time slots is, and guessing by position would quietly put a
     * dropoff time on a pickup
     */
    public function testTimeSlotsAreNotAssignedByPosition()
    {
        $mapping = $this->detect(
            ['Col A', 'Col B'],
            [
                ['2019-12-12 10:00 - 2019-12-12 11:00', '2019-12-12 12:00 - 2019-12-12 13:00'],
                ['2019-12-13 10:00 - 2019-12-13 11:00', '2019-12-13 12:00 - 2019-12-13 13:00'],
            ]
        );

        $this->assertSame([], $mapping);
    }

    public function testValuesFillTheOneFieldLeftOfTheirType()
    {
        // The dropoff time slot is named, so the unnamed column of time slots can only be the pickup's
        $mapping = $this->detect(
            ['Créneau livraison', 'Col B'],
            [
                ['2019-12-12 12:00 - 2019-12-12 13:00', '2019-12-12 10:00 - 2019-12-12 11:00'],
                ['2019-12-13 12:00 - 2019-12-13 13:00', '2019-12-13 10:00 - 2019-12-13 11:00'],
            ]
        );

        $this->assertSame([
            0 => 'dropoff.timeslot',
            1 => 'pickup.timeslot',
        ], $mapping);
    }

    public function testPhoneNumbersAreDetectedByValue()
    {
        $mapping = $this->detect(
            ['Telephone livraison', 'Col B'],
            [
                ['+33612345678', '+33690210729'],
                ['+33612345679', '+33690210730'],
            ]
        );

        $this->assertSame([
            0 => 'dropoff.address.telephone',
            1 => 'pickup.address.telephone',
        ], $mapping);
    }

    public function testPackagesAreDetectedByValue()
    {
        $mapping = $this->detect(['Col A'], [['small-box=1 big-box=2'], ['small-box=3']]);

        $this->assertSame([0 => 'dropoff.packages'], $mapping);
    }

    /**
     * One value of the wrong shape and the column is not what it looked like
     */
    public function testValuesMustAllLookAlike()
    {
        $mapping = $this->detect(['Col A'], [['small-box=1'], ['two big boxes please']]);

        $this->assertSame([], $mapping);
    }

    /**
     * Nothing can be told from an address, and geocoding a sample on a web request is out
     */
    public function testAddressesAreNotDetectedByValue()
    {
        $mapping = $this->detect(['Col A'], [['24 rue de rivoli paris'], ['58 av parmentier paris']]);

        $this->assertSame([], $mapping);
    }

    public function testEmptyColumnsAreIgnored()
    {
        $mapping = $this->detect(['', 'Poids'], [['', '5.5']]);

        $this->assertSame([1 => 'weight'], $mapping);
    }
}
