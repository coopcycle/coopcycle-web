<?php

declare(strict_types=1);

namespace Tests\AppBundle\Integration\Rdc\Api;

use AppBundle\Integration\Rdc\Api\RdcClientFactory;
use AppBundle\Integration\Rdc\Api\RdcClientInterface;
use AppBundle\Integration\Rdc\Api\RdcServiceFacade;
use AppBundle\Integration\Rdc\DTO\RdcApiServiceRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\ResponseInterface;

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
            ->with('BOL.MEMBER.PROVIDER')
            ->willReturn($this->createMock(RdcClientInterface::class));

        $facade = new RdcServiceFacade($factory, new NullLogger(), 'BOL.MEMBER.PROVIDER');

        $this->assertSame($facade->getRdcClient(), $facade->getRdcClient());
    }

    public function testBuildServiceDataUsesTheServiceRequestRequestor(): void
    {
        $facade = new RdcServiceFacade($this->createMock(RdcClientFactory::class), new NullLogger(), '');
        $apiRequest = RdcApiServiceRequest::parse([
            'requestor' => ['legalEntityName' => 'REQUESTOR'],
        ]);

        $serviceData = $facade->buildServiceData('42', $apiRequest, 'C-26-1234-1');

        $this->assertSame(['legalEntityName' => 'REQUESTOR'], $serviceData['requestor']);
        $this->assertSame('livraison REQUESTOR n° 42', $serviceData['serviceName']);
    }

    public function testBuildServiceDataRequiresARequestor(): void
    {
        $facade = new RdcServiceFacade($this->createMock(RdcClientFactory::class), new NullLogger(), '');

        $this->expectException(\InvalidArgumentException::class);

        $facade->buildServiceData('42', RdcApiServiceRequest::parse([]));
    }

    public function testLinksTheActivityAsAListOfLinks(): void
    {
        $client = $this->clientRespondingWith(200);
        $client->expects($this->once())
            ->method('patch')
            ->with('/services/42', ['scheduledActivities' => [['uri' => 'https://bol-api.coopcycle.example.com/activities/7.transport']]]);

        $this->facadeFor($client, new NullLogger())->linkActivityToService('42', '7.transport');
    }

    public function testARefusedLinkIsLoggedWithoutFailing(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('Failed to link activity to service', $this->callback(
                fn (array $context) => 400 === $context['status'] && '{"message":"Bad Request"}' === $context['response_body']
            ));

        $this->facadeFor($this->clientRespondingWith(400, '{"message":"Bad Request"}'), $logger)
            ->linkActivityToService('42', '7.transport');
    }

    private function clientRespondingWith(int $statusCode, string $body = '{}'): RdcClientInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($statusCode);
        $response->method('getContent')->willReturn($body);

        $client = $this->createMock(RdcClientInterface::class);
        $client->method('getBaseUrl')->willReturn('https://bol-api.coopcycle.example.com');
        $client->method('patch')->willReturn($response);

        return $client;
    }

    private function facadeFor(RdcClientInterface $client, LoggerInterface $logger): RdcServiceFacade
    {
        $factory = $this->createMock(RdcClientFactory::class);
        $factory->method('create')->willReturn($client);

        return new RdcServiceFacade($factory, $logger, 'BOL.MEMBER.PROVIDER');
    }
}
