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
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class RdcPickupStatusUpdateMessageHandlerTest extends TestCase
{
    private const LO_URI = 'https://bol-api.nwp.example.com/service-requests/C-26-1234-1';

    public function testARefusedRevisionReadFailsTheCancellation(): void
    {
        $store = new Store();
        $store->setRdcConnectionId('BOL.MEMBER.NOWASTEPHARMA');

        $delivery = new Delivery();
        $delivery->setStore($store);
        $delivery->setMetadata('rdc.lo_uri', self::LO_URI);
        $pickup = $delivery->getPickup();
        $pickup->setAddress(new Address());

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('find')->with(Task::class, 1)->willReturn($pickup);

        $rdcClient = $this->createMock(RdcClientInterface::class);
        $refused = $this->createMock(ResponseInterface::class);
        $refused->method('getStatusCode')->willReturn(403);
        $rdcClient->method('getRemote')->with(self::LO_URI)->willReturn($refused);
        // Without a revision there is nothing to post
        $rdcClient->expects($this->never())->method('postRemote');

        $rdcClientFactory = $this->createMock(RdcClientFactory::class);
        $rdcClientFactory->method('create')->with('BOL.MEMBER.NOWASTEPHARMA')->willReturn($rdcClient);

        $handler = new RdcPickupStatusUpdateMessageHandler(
            $entityManager,
            $rdcClientFactory,
            new NullLogger(),
            $this->createMock(UrlGeneratorInterface::class),
            new RdcEventDispatcher(new NullLogger()),
            true,
        );

        // Failing the message lets Messenger retry, instead of skipping the cancellation
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('HTTP 403');

        $handler(new RdcPickupStatusUpdateMessage(1, Task::STATUS_CANCELLED));
    }
}
