<?php

declare(strict_types=1);

namespace Tests\AppBundle\Integration\Rdc\Api;

use AppBundle\Integration\Rdc\Api\RdcClientFactory;
use AppBundle\Integration\Rdc\Api\RdcClientInterface;
use AppBundle\Integration\Rdc\Api\RdcServiceFacade;
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

    public function testFallsBackToTheFirstConnectionWithoutOne(): void
    {
        $client = $this->createMock(RdcClientInterface::class);

        $factory = $this->createMock(RdcClientFactory::class);
        $factory->method('getConnectionIds')->willReturn(['BOL.MEMBER.APPLICOLIS', 'BOL.MEMBER.OTHER_PROVIDER']);
        $factory->expects($this->once())
            ->method('create')
            ->with('BOL.MEMBER.APPLICOLIS')
            ->willReturn($client);

        $facade = new RdcServiceFacade($factory, new NullLogger(), '');

        $this->assertSame($client, $facade->getRdcClient());
    }
}
