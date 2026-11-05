<?php

declare(strict_types=1);

namespace Tests\AppBundle\MessageHandler;

use AppBundle\Entity\Address;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Store;
use AppBundle\Entity\Task;
use AppBundle\Integration\Rdc\Api\RdcClientFactory;
use AppBundle\Integration\Rdc\Api\RdcClientInterface;
use AppBundle\Integration\Rdc\RdcEventDispatcher;
use AppBundle\Message\RdcPickupStatusUpdateMessage;
use AppBundle\MessageHandler\RdcPickupStatusUpdateMessageHandler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class RdcPickupStatusUpdateMessageHandlerTest extends TestCase
{
    private const LO_URI = 'https://bol-api.requestor.example.com/service-requests/C-26-1234-1';

    private RdcClientInterface&MockObject $rdcClient;
    private RdcPickupStatusUpdateMessageHandler $handler;

    protected function setUp(): void
    {
        $store = new Store();
        $store->setRdcConnectionId('BOL.MEMBER.PROVIDER');

        $delivery = new Delivery();
        $delivery->setStore($store);
        $delivery->setMetadata('rdc.lo_uri', self::LO_URI);
        $pickup = $delivery->getPickup();
        $pickup->setAddress(new Address());

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('find')->with(Task::class, 1)->willReturn($pickup);

        $this->rdcClient = $this->createMock(RdcClientInterface::class);

        $rdcClientFactory = $this->createMock(RdcClientFactory::class);
        $rdcClientFactory->method('create')->with('BOL.MEMBER.PROVIDER')->willReturn($this->rdcClient);

        $this->handler = new RdcPickupStatusUpdateMessageHandler(
            $entityManager,
            $rdcClientFactory,
            new NullLogger(),
            $this->createMock(UrlGeneratorInterface::class),
            new RdcEventDispatcher(new NullLogger()),
            true,
        );
    }

    public function testARefusedRevisionReadFailsTheCancellation(): void
    {
        $this->rdcClient->method('patch')->willReturn($this->response(200));
        $this->rdcClient->method('getRemote')->with(self::LO_URI)->willReturn($this->response(403));
        // Without a revision there is nothing to post
        $this->rdcClient->expects($this->never())->method('postRemote');

        // Failing the message lets Messenger retry, instead of skipping the cancellation
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('HTTP 403');

        ($this->handler)(new RdcPickupStatusUpdateMessage(1, Task::STATUS_CANCELLED));
    }

    public function testARefusedStatusUpdateFailsTheMessage(): void
    {
        // The BOL was down when a pickup was done: the update must be retried, not lost
        $this->rdcClient->method('patch')->willReturn($this->response(503, 'no available server'));
        $this->rdcClient->expects($this->never())->method('post');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('HTTP 503 no available server');

        ($this->handler)(new RdcPickupStatusUpdateMessage(1, Task::STATUS_DONE));
    }

    public function testARefusedEventFailsTheMessage(): void
    {
        $this->rdcClient->method('patch')->willReturn($this->response(200));
        $this->rdcClient->method('post')->willReturn($this->response(400, '{"title":"Bad Request"}'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('HTTP 400');

        ($this->handler)(new RdcPickupStatusUpdateMessage(1, Task::STATUS_DONE));
    }

    private function response(int $statusCode, string $body = '{}'): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($statusCode);
        $response->method('getContent')->willReturn($body);

        return $response;
    }
}
