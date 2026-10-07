<?php

namespace Tests\AppBundle\Transporter;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Service\Geocoder;
use AppBundle\Transporter\ImportFromPoint;
use libphonenumber\PhoneNumberUtil;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use Transporter\Enum\NameAndAddressType;
use Transporter\Enum\TransporterName;
use Transporter\Transporter;

class ImportFromPointTest extends TestCase
{
    use ProphecyTrait;

    const EDI_SAMPLE = <<<EDI
    UNA:+,? ' UNB+UNOC:1+123456789:22+987654321:22+240325:1951+2206' UNH+1+SCONTR:3:2:GT:GTF210+ACG' BGM++240325' NAD+FW+12345678900935:05++DBSCHENKER TESTING INC' DTM+DEP+240325' NAD+DP+98765432100010:05++COOPCYCLE TESTING INC' TSR+++3' CAG+P+V' TDT++++3' DOC+730+++ACG+2278663' UNS+D' RFF+CN+JOY0123456789' GID++1:23+1:21' MSE+CGW+15:KG' NAD+CN+++JOHN DOE:ZIMP COMPANY+7 RUE DES FONTAINES+LORIENT++56100+FR' CTA+IC+:JOHN DOE+06 01 02 03 04:AL' NAD+CO+++HOME DEPOT+54 ROUTE DE TREGUIER:BP 8+LOUANNEC++22+FR' DTM+DES+240322' NAD+FW+12345678900935:05++DBSCHENKER TESTING INC+LE BREHAT:ALLEE DES CHATELETS+PLOUFRAGAN++22440+FR' CAG+P+V+++++++++227004' TSR++D:E+3' GDS+G+DIVERS' PCI+23' GIN+BN+*2222121907222700470100691001300' DOC+WBL::JOY0123456789+++ACG+70100691+219072' DOC+824+++PRI+FRSBK830689437' UNS+S' UNT+26+1' UNZ+1+2206'
    EDI;

    public function testCountryCodeIsNotSentToTheGeocoder(): void
    {
        $geocoded = new Address();
        $geocoded->setGeo(new GeoCoordinates(47.75, -3.37));
        $geocoded->setStreetAddress('7 Rue des Fontaines, 56100 Lorient');

        $geocoder = $this->prophesize(Geocoder::class);
        $geocoder->geocode('7 RUE DES FONTAINES LORIENT 56100')
            ->shouldBeCalled()
            ->willReturn($geocoded);

        $import = new ImportFromPoint(
            $geocoder->reveal(),
            PhoneNumberUtil::getInstance(),
            new NullLogger()
        );
        $import->setDefaultCoordinates(new GeoCoordinates(47.75, -3.37));

        [, $messages] = Transporter::parse(self::EDI_SAMPLE, TransporterName::DBSCHENKER);
        $point = $messages[0]->getTasks()[0];

        $nad = $point->getNamesAndAddresses(NameAndAddressType::RECIPIENT)[0];
        $this->assertEquals('7 RUE DES FONTAINES', $nad->getStreet());
        $this->assertEquals('LORIENT', $nad->getCity());
        $this->assertEquals('56100', $nad->getPostalCode());
        $this->assertEquals('FR', $nad->getCountryCode());

        $task = $import->import($point);

        $this->assertEquals('7 Rue des Fontaines, 56100 Lorient', $task->getAddress()->getStreetAddress());
        $this->assertStringContainsString(
            "\n7 RUE DES FONTAINES LORIENT 56100\n",
            $task->getMetadata()['imported_from']
        );
    }
}
