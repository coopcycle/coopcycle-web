<?php

declare(strict_types=1);

namespace Tests\AppBundle\Integration\Rdc\Api;

use AppBundle\Integration\Rdc\Api\RdcClientFactory;
use AppBundle\Integration\Rdc\Api\RdcClientInterface;
use AppBundle\Integration\Rdc\Api\RdcServiceFacade;
use AppBundle\Integration\Rdc\DTO\RdcApiServiceRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class RdcServiceFacadeTest extends TestCase
{
    public function testForConnectionUsesTheGivenConnection(): void
    {
        $client = $this->createMock(RdcClientInterface::class);

        $factory = $this->createMock(RdcClientFactory::class);
        $factory->expects($this->once())
            ->method('create')
            ->with('BOL.MEMBER.OTHER_PROVIDER')
            ->willReturn($client);

        $facade = new RdcServiceFacade($factory, new NullLogger(), '');
        $bound = $facade->forConnection('BOL.MEMBER.OTHER_PROVIDER');

        $this->assertSame('BOL.MEMBER.OTHER_PROVIDER', $bound->getConnectionId());
        $this->assertSame($client, $bound->getRdcClient());

        // The shared facade is left untouched
        $this->assertNotSame($facade, $bound);
        $this->assertSame('', $facade->getConnectionId());
    }

    public function testTheSharedFacadeHasNoConnectionToFallBackOn(): void
    {
        $factory = $this->createMock(RdcClientFactory::class);
        $factory->expects($this->never())->method('create');

        $facade = new RdcServiceFacade($factory, new NullLogger(), '');

        $this->expectException(\LogicException::class);

        $facade->getRdcClient();
    }

    public function testReusesTheClientOfItsConnection(): void
    {
        $factory = $this->createMock(RdcClientFactory::class);
        $factory->expects($this->once())
            ->method('create')
            ->with('BOL.MEMBER.APPLICOLIS')
            ->willReturn($this->createMock(RdcClientInterface::class));

        $facade = new RdcServiceFacade($factory, new NullLogger(), 'BOL.MEMBER.APPLICOLIS');

        $this->assertSame($facade->getRdcClient(), $facade->getRdcClient());
    }

    public function testBuildServiceDataUsesTheServiceRequestRequestor(): void
    {
        $facade = new RdcServiceFacade($this->createMock(RdcClientFactory::class), new NullLogger(), '');
        $apiRequest = RdcApiServiceRequest::parse([
            'requestor' => ['legalEntityName' => 'NOWASTEPHARMA'],
        ]);

        $serviceData = $facade->buildServiceData('42', $apiRequest, 'C-26-1234-1');

        $this->assertSame(['legalEntityName' => 'NOWASTEPHARMA'], $serviceData['requestor']);
        $this->assertSame('livraison NOWASTEPHARMA n° 42', $serviceData['serviceName']);
    }

    public function testBuildServiceDataRequiresARequestor(): void
    {
        $facade = new RdcServiceFacade($this->createMock(RdcClientFactory::class), new NullLogger(), '');

        $this->expectException(\InvalidArgumentException::class);

        $facade->buildServiceData('42', RdcApiServiceRequest::parse([]));
    }
}
