<?php

namespace AppBundle\ExpressionLanguage;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Zone;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class ZoneExpressionLanguageProviderTest extends TestCase
{
    use ProphecyTrait;

    private $language;
    private $zone;

    public function setUp(): void
    {
        $this->language = new ExpressionLanguage();

        $geojson = json_decode(file_get_contents(realpath(__DIR__ . '/../Resources/geojson/paris_south_area.geojson')), true);

        $zone = new Zone();
        $zone->setGeoJSON($geojson);

        $this->zoneRepository = $this->prophesize(EntityRepository::class);

        $this->zoneRepository
            ->findOneBy(['name' => 'paris_south_area'])
            ->willReturn($zone);

        $this->zoneRepository
            ->findOneBy(['name' => 'no_go_zone'])
            ->willReturn(null);
    }

    public function testInZoneReturnsTrue()
    {
        $this->language->registerProvider(new ZoneExpressionLanguageProvider($this->zoneRepository->reveal()));

        $address = new Address();
        $address->setGeo(new GeoCoordinates(48.842049, 2.331181));

        $this->assertTrue($this->language->evaluate('in_zone(address, zone)', [
            'address' => $address,
            'zone' => 'paris_south_area',
        ]));

        $this->assertTrue($this->language->evaluate('in_zone(address, "paris_south_area")', [
            'address' => $address,
        ]));

        // Test with dot notation

        $pickup = new \stdClass();
        $pickup->address = $address;

        $this->assertTrue($this->language->evaluate('in_zone(pickup.address, zone)', [
            'pickup' => $pickup,
            'zone' => 'paris_south_area',
        ]));

        $this->assertTrue($this->language->evaluate('in_zone(pickup.address, "paris_south_area")', [
            'pickup' => $pickup,
        ]));
    }

    public function testInZoneReturnsFalse()
    {
        $this->language->registerProvider(new ZoneExpressionLanguageProvider($this->zoneRepository->reveal()));

        $address = new Address();
        $address->setGeo(new GeoCoordinates(48.887366, 2.370027));

        $this->assertFalse($this->language->evaluate('in_zone(address, zone)', [
            'address' => $address,
            'zone' => 'paris_south_area',
        ]));

        $this->assertFalse($this->language->evaluate('in_zone(address, "paris_south_area")', [
            'address' => $address,
        ]));
    }

    public function testOutZoneReturnsTrue()
    {
        $this->language->registerProvider(new ZoneExpressionLanguageProvider($this->zoneRepository->reveal()));

        // Métro Porte de la Chapelle
        $address = new Address();
        $address->setGeo(new GeoCoordinates(48.897950, 2.359177));

        $this->assertTrue($this->language->evaluate('out_zone(address, zone)', [
            'address' => $address,
            'zone' => 'paris_south_area',
        ]));

        $this->assertTrue($this->language->evaluate('out_zone(address, "paris_south_area")', [
            'address' => $address,
        ]));

        // Test with dot notation

        $pickup = new \stdClass();
        $pickup->address = $address;

        $this->assertTrue($this->language->evaluate('out_zone(pickup.address, zone)', [
            'pickup' => $pickup,
            'zone' => 'paris_south_area',
        ]));

        $this->assertTrue($this->language->evaluate('out_zone(pickup.address, "paris_south_area")', [
            'pickup' => $pickup,
        ]));
    }

    public function testOutZoneReturnsFalse()
    {
        $this->language->registerProvider(new ZoneExpressionLanguageProvider($this->zoneRepository->reveal()));

        $address = new Address();
        $address->setGeo(new GeoCoordinates(48.842049, 2.331181));

        $this->assertFalse($this->language->evaluate('out_zone(address, zone)', [
            'address' => $address,
            'zone' => 'paris_south_area',
        ]));

        $this->assertFalse($this->language->evaluate('out_zone(address, "paris_south_area")', [
            'address' => $address,
        ]));
    }

    public function testNonExistentZone()
    {
        $this->language->registerProvider(new ZoneExpressionLanguageProvider($this->zoneRepository->reveal()));

        $address = new Address();
        $address->setGeo(new GeoCoordinates(48.887366, 2.370027));

        $this->assertFalse($this->language->evaluate('in_zone(address, "no_go_zone")', [
            'address' => $address,
        ]));
    }

    public function testNullAddress()
    {
        $this->language->registerProvider(new ZoneExpressionLanguageProvider($this->zoneRepository->reveal()));

        $this->assertFalse($this->language->evaluate('in_zone(address, zone)', [
            'address' => null,
            'zone' => 'paris_south_area',
        ]));

        $this->assertFalse($this->language->evaluate('out_zone(address, zone)', [
            'address' => null,
            'zone' => 'paris_south_area',
        ]));
    }
}
