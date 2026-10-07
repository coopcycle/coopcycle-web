<?php

namespace Tests\AppBundle\Transporter;

use AppBundle\Action\Incident\CreateIncident;
use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Incident\Incident;
use AppBundle\Service\Geocoder;
use AppBundle\Transporter\ImportFromPoint;
use libphonenumber\PhoneNumberUtil;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use Symfony\Component\Translation\IdentityTranslator;
use Transporter\DTO\Point;
use Transporter\Enum\NameAndAddressType;
use Transporter\Enum\TransporterName;
use Transporter\Transporter;

class ImportFromPointTest extends TestCase
{
    use ProphecyTrait;

    const EDI_SAMPLE = <<<EDI
    UNA:+,? ' UNB+UNOC:1+123456789:22+987654321:22+240325:1951+2206' UNH+1+SCONTR:3:2:GT:GTF210+ACG' BGM++240325' NAD+FW+12345678900935:05++DBSCHENKER TESTING INC' DTM+DEP+240325' NAD+DP+98765432100010:05++COOPCYCLE TESTING INC' TSR+++3' CAG+P+V' TDT++++3' DOC+730+++ACG+2278663' UNS+D' RFF+CN+JOY0123456789' GID++1:23+1:21' MSE+CGW+15:KG' NAD+CN+++JOHN DOE:ZIMP COMPANY+7 RUE DES FONTAINES+LORIENT++56100+FR' CTA+IC+:JOHN DOE+06 01 02 03 04:AL' NAD+CO+++HOME DEPOT+54 ROUTE DE TREGUIER:BP 8+LOUANNEC++22+FR' DTM+DES+240322' NAD+FW+12345678900935:05++DBSCHENKER TESTING INC+LE BREHAT:ALLEE DES CHATELETS+PLOUFRAGAN++22440+FR' CAG+P+V+++++++++227004' TSR++D:E+3' GDS+G+DIVERS' PCI+23' GIN+BN+*2222121907222700470100691001300' DOC+WBL::JOY0123456789+++ACG+70100691+219072' DOC+824+++PRI+FRSBK830689437' UNS+S' UNT+26+1' UNZ+1+2206'
    EDI;

    private $geocoder;
    private $createIncident;
    private ImportFromPoint $import;

    public function setUp(): void
    {
        $this->geocoder = $this->prophesize(Geocoder::class);
        $this->createIncident = $this->prophesize(CreateIncident::class);

        $this->import = new ImportFromPoint(
            $this->geocoder->reveal(),
            PhoneNumberUtil::getInstance(),
            new NullLogger(),
            $this->createIncident->reveal(),
            new IdentityTranslator()
        );
        // Lorient
        $this->import->setDefaultCoordinates(new GeoCoordinates(47.75, -3.37));
    }

    private function point(): Point
    {
        [, $messages] = Transporter::parse(self::EDI_SAMPLE, TransporterName::DBSCHENKER);

        return $messages[0]->getTasks()[0];
    }

    private function geocodeTo(?Address $address): void
    {
        $this->geocoder->geocode('7 RUE DES FONTAINES LORIENT 56100')
            ->shouldBeCalled()
            ->willReturn($address);
    }

    private function address(string $streetAddress, float $latitude, float $longitude): Address
    {
        $address = new Address();
        $address->setGeo(new GeoCoordinates($latitude, $longitude));
        $address->setStreetAddress($streetAddress);

        return $address;
    }

    private function expectIncident(): object
    {
        $reported = new \stdClass();
        $this->createIncident->__invoke(Argument::type(Incident::class), null)
            ->shouldBeCalledOnce()
            ->will(function ($args) use ($reported) {
                $reported->incident = $args[0];

                return $args[0];
            });

        return $reported;
    }

    public function testCountryCodeIsNotSentToTheGeocoder(): void
    {
        $this->geocodeTo($this->address('7 Rue des Fontaines, 56100 Lorient', 47.75, -3.37));
        $this->createIncident->__invoke(Argument::cetera())->shouldNotBeCalled();

        $point = $this->point();

        $nad = $point->getNamesAndAddresses(NameAndAddressType::RECIPIENT)[0];
        $this->assertEquals('7 RUE DES FONTAINES', $nad->getStreet());
        $this->assertEquals('LORIENT', $nad->getCity());
        $this->assertEquals('56100', $nad->getPostalCode());
        $this->assertEquals('FR', $nad->getCountryCode());

        $task = $this->import->import($point);
        $this->import->reportAddressIssue($task);

        $this->assertEquals('7 Rue des Fontaines, 56100 Lorient', $task->getAddress()->getStreetAddress());
        $this->assertStringContainsString(
            "\n7 RUE DES FONTAINES LORIENT 56100\n",
            $task->getMetadata()['imported_from']
        );
    }

    public function testResultOutOfRangeIsReportedAsIncident(): void
    {
        // Paris, far from Lorient
        $this->geocodeTo($this->address('7 Rue des Fontaines, 75019 Paris', 48.88, 2.38));
        $reported = $this->expectIncident();

        $task = $this->import->import($this->point());
        $this->import->reportAddressIssue($task);
        // Reported once
        $this->import->reportAddressIssue($task);

        $this->assertEquals('INVALID ADDRESS', $task->getAddress()->getStreetAddress());
        $this->assertEmpty($task->getTags());

        $incident = $reported->incident;
        $this->assertSame($task, $incident->getTask());
        $this->assertEquals('ADDRESS_REVIEW_NEEDED', $incident->getFailureReasonCode());
        $this->assertEquals(Incident::PRIORITY_HIGH, $incident->getPriority());
        $this->assertEquals('transporter.address_review.title', $incident->getTitle());
        $this->assertEquals('transporter.address_review.out_of_range', $incident->getDescription());
        $this->assertEquals([[
            'transporter_address' => '7 RUE DES FONTAINES LORIENT 56100',
            'rejected_address' => '7 Rue des Fontaines, 75019 Paris',
        ]], $incident->getMetadata());
    }

    public function testNoResultIsReportedAsIncident(): void
    {
        $this->geocodeTo(null);
        $reported = $this->expectIncident();

        $task = $this->import->import($this->point());
        $this->import->reportAddressIssue($task);

        $this->assertEquals('INVALID ADDRESS', $task->getAddress()->getStreetAddress());
        $this->assertEquals('transporter.address_review.not_found', $reported->incident->getDescription());
        $this->assertEquals([[
            'transporter_address' => '7 RUE DES FONTAINES LORIENT 56100',
        ]], $reported->incident->getMetadata());
    }
}
