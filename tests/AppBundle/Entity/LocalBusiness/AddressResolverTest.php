<?php

namespace Tests\AppBundle\Entity\LocalBusiness;

use AppBundle\Entity\Address;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\LocalBusiness\AddressResolver;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class AddressResolverTest extends TestCase
{
    use ProphecyTrait;

    protected function setUp(): void
    {

    }

    public function testReturnsDefaultAddress(): void
    {
        $address = new Address();

        $shop = new LocalBusiness();
        $shop->setAddress($address);

        $this->assertSame($address, AddressResolver::resolveAddress($shop));
    }

    public function testReturnsDifferentAddressForMonday(): void
    {
        $defaultAddresss = new Address();

        $shop = new LocalBusiness();
        $shop->setAddress($defaultAddresss);

        $mondayAddress = new Address();

        $shop->addAddressForDayOfWeek('Mo', $mondayAddress);

        $this->assertSame($defaultAddresss, AddressResolver::resolveAddress($shop));

        $monday = new \DateTimeImmutable('2026-03-09');
        $tuesday = new \DateTimeImmutable('2026-03-10');
        $wednesday = new \DateTimeImmutable('2026-03-11');

        $this->assertSame($mondayAddress, AddressResolver::resolveAddress($shop, $monday));
        $this->assertSame($defaultAddresss, AddressResolver::resolveAddress($shop, $tuesday));
        $this->assertSame($defaultAddresss, AddressResolver::resolveAddress($shop, $wednesday));
    }

    public function testReturnsDifferentAddressForMondayOrTuesday(): void
    {
        $defaultAddresss = new Address();

        $shop = new LocalBusiness();
        $shop->setAddress($defaultAddresss);

        $mondayTuesdayAddress = new Address();
        $shop->addAddressForDayOfWeek('Mo,Tu', $mondayTuesdayAddress);

        $monday = new \DateTimeImmutable('2026-03-09');
        $tuesday = new \DateTimeImmutable('2026-03-10');
        $wednesday = new \DateTimeImmutable('2026-03-11');

        $this->assertSame($mondayTuesdayAddress, AddressResolver::resolveAddress($shop, $monday));
        $this->assertSame($mondayTuesdayAddress, AddressResolver::resolveAddress($shop, $tuesday));
        $this->assertSame($defaultAddresss, AddressResolver::resolveAddress($shop, $wednesday));
    }
}
