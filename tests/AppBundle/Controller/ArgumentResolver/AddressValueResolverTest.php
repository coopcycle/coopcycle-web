<?php

declare(strict_types=1);

namespace Tests\AppBundle\Controller\ArgumentResolver;

use ApiPlatform\Api\IriConverterInterface;
use AppBundle\Controller\ArgumentResolver\AddressValueResolver;
use AppBundle\Entity\Address;
use AppBundle\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class AddressValueResolverTest extends TestCase
{
    use ProphecyTrait;

    // A saved address shared in a link, as serialized by the API (JSON-LD)
    const JSONLD_ADDRESS = <<<JSON
    {"@id":"/api/addresses/123","@type":"http://schema.org/Place","geo":{"latitude":53.4459,"longitude":-2.2731},"streetAddress":"12 Beech Road, Manchester M21 9EL, UK","telephone":null,"name":null,"description":null}
    JSON;

    // An address picked in the autocomplete
    const FLAT_ADDRESS = <<<JSON
    {"streetAddress":"12 Beech Road, Manchester M21 9EL, UK","latitude":53.4459,"longitude":-2.2731,"geohash":"gcw2hzbcr","provider":"MAP_PICKER"}
    JSON;

    const NO_COORDINATES_ADDRESS = <<<JSON
    {"@id":"/api/addresses/123","streetAddress":"12 Beech Road, Manchester M21 9EL, UK"}
    JSON;

    private function encode(string $json): string
    {
        // Same as btoa(encodeURIComponent(JSON.stringify(address))) in js/app/search/address.js
        return base64_encode(rawurlencode($json));
    }

    private function resolve(?string $value, ?User $user = null): ?Address
    {
        $security = $this->prophesize(Security::class);
        $security->getUser()->willReturn($user);

        $iriConverter = $this->prophesize(IriConverterInterface::class);
        $iriConverter->getResourceFromIri(Argument::any())->willReturn(new Address());

        $resolver = new AddressValueResolver($security->reveal(), $iriConverter->reveal());

        $query = is_null($value) ? [] : ['address' => $value];
        $request = new Request($query, [], ['_route' => 'restaurant']);
        $argument = new ArgumentMetadata('address', Address::class, false, true, null, true);

        $this->assertTrue($resolver->supports($request, $argument));

        $values = iterator_to_array($resolver->resolve($request, $argument));

        $this->assertCount(1, $values);

        return $values[0];
    }

    public function testJsonLdAddressForAnonymousUser(): void
    {
        $address = $this->resolve($this->encode(self::JSONLD_ADDRESS));

        $this->assertInstanceOf(Address::class, $address);
        $this->assertEquals('12 Beech Road, Manchester M21 9EL, UK', $address->getStreetAddress());
        $this->assertNotNull($address->getGeo());
        $this->assertEquals(53.4459, $address->getGeo()->getLatitude());
        $this->assertEquals(-2.2731, $address->getGeo()->getLongitude());
    }

    public function testJsonLdAddressForUserWhoIsNotTheOwner(): void
    {
        $user = $this->prophesize(User::class);
        $user->getAddresses()->willReturn(new ArrayCollection([ new Address() ]));

        $address = $this->resolve($this->encode(self::JSONLD_ADDRESS), $user->reveal());

        $this->assertInstanceOf(Address::class, $address);
        $this->assertNotNull($address->getGeo());
        $this->assertEquals(53.4459, $address->getGeo()->getLatitude());
        $this->assertEquals(-2.2731, $address->getGeo()->getLongitude());
    }

    public function testFlatAddress(): void
    {
        $address = $this->resolve($this->encode(self::FLAT_ADDRESS));

        $this->assertInstanceOf(Address::class, $address);
        $this->assertEquals('12 Beech Road, Manchester M21 9EL, UK', $address->getStreetAddress());
        $this->assertEquals(53.4459, $address->getGeo()->getLatitude());
        $this->assertEquals(-2.2731, $address->getGeo()->getLongitude());
        $this->assertEquals(Address::PROVIDER_MAPPICKER, $address->getProvider());
    }

    public function testAddressWithoutCoordinatesYieldsNull(): void
    {
        $this->assertNull($this->resolve($this->encode(self::NO_COORDINATES_ADDRESS)));
    }

    public function testInvalidValueYieldsNull(): void
    {
        $this->assertNull($this->resolve('not-base64-json'));
    }

    public function testMissingValueYieldsNull(): void
    {
        $this->assertNull($this->resolve(null));
        $this->assertNull($this->resolve(''));
    }
}
