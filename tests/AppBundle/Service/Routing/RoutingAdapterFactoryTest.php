<?php

namespace Tests\AppBundle\Service\Routing;

use AppBundle\Service\Routing\Fallback;
use AppBundle\Service\Routing\OsrmWithFallback;
use AppBundle\Service\Routing\RoutingAdapterFactory;
use AppBundle\Service\Routing\ValhallaWithFallback;
use AppBundle\Service\RoutingInterface;
use PHPUnit\Framework\TestCase;

class RoutingAdapterFactoryTest extends TestCase
{
    use \Prophecy\PhpUnit\ProphecyTrait;

    public function testOsrmIsTheDefault()
    {
        $factory = new RoutingAdapterFactory(
            $this->makeOsrm(),
            $this->makeValhalla(),
        );

        $this->assertInstanceOf(OsrmWithFallback::class, $factory->create('osrm'));
        $this->assertInstanceOf(OsrmWithFallback::class, $factory->create(''));
        $this->assertInstanceOf(OsrmWithFallback::class, $factory->create('OSRM'));
    }

    public function testValhallaSelector()
    {
        $factory = new RoutingAdapterFactory(
            $this->makeOsrm(),
            $this->makeValhalla(),
        );

        $this->assertInstanceOf(ValhallaWithFallback::class, $factory->create('valhalla'));
        $this->assertInstanceOf(ValhallaWithFallback::class, $factory->create('Valhalla'));
    }

    public function testUnknownEngineThrows()
    {
        $factory = new RoutingAdapterFactory(
            $this->makeOsrm(),
            $this->makeValhalla(),
        );

        $this->expectException(\InvalidArgumentException::class);
        $factory->create('mapbox');
    }

    private function makeOsrm(): RoutingInterface
    {
        $engineProphecy = $this->prophesize(\AppBundle\Service\Routing\Engine\OsrmRoutingEngine::class);
        return new OsrmWithFallback(
            new \AppBundle\Service\Routing\Osrm($engineProphecy->reveal()),
            new Fallback(),
        );
    }

    private function makeValhalla(): RoutingInterface
    {
        $engineProphecy = $this->prophesize(\AppBundle\Service\Routing\Engine\ValhallaRoutingEngine::class);
        return new ValhallaWithFallback(
            new \AppBundle\Service\Routing\Valhalla($engineProphecy->reveal()),
            new Fallback(),
        );
    }
}
