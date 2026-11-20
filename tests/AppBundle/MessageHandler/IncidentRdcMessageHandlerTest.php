<?php

declare(strict_types=1);

namespace Tests\AppBundle\MessageHandler;

use AppBundle\Entity\Address;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Incident\Incident;
use AppBundle\Entity\Store;
use AppBundle\Integration\Rdc\Api\RdcClientFactory;
use AppBundle\Integration\Rdc\Api\RdcClientInterface;
use AppBundle\Integration\Rdc\RdcEventDispatcher;
use AppBundle\Integration\Rdc\RdcTaskContextResolver;
use AppBundle\Message\IncidentRdcMessage;
use AppBundle\MessageHandler\IncidentRdcMessageHandler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class IncidentRdcMessageHandlerTest extends TestCase
{
    private const DELIVERY_ID = 10;
    private const PICKUP_ID = 20;

    private RdcClientInterface&MockObject $rdcClient;
    private Incident $incident;
    private IncidentRdcMessageHandler $handler;

    protected function setUp(): void
    {
        $store = new Store();
        $store->setRdcConnectionId('BOL.MEMBER.PROVIDER');

        $delivery = new Delivery();
        $delivery->setStore($store);
        $this->setId($delivery, self::DELIVERY_ID);
        $this->setId($delivery->getPickup(), self::PICKUP_ID);
        $delivery->getDropoff()->setAddress(new Address());

        $this->incident = new Incident();
        $this->incident->setTitle('Recipient absent');
        $this->incident->setTask($delivery->getDropoff());

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('find')->with(Incident::class, 1)->willReturn($this->incident);

        $this->rdcClient = $this->createMock(RdcClientInterface::class);

        $rdcClientFactory = $this->createMock(RdcClientFactory::class);
        $rdcClientFactory->method('create')->with('BOL.MEMBER.PROVIDER')->willReturn($this->rdcClient);

        $this->handler = new IncidentRdcMessageHandler(
            $entityManager,
            new RdcTaskContextResolver($rdcClientFactory),
            $this->createMock(UrlGeneratorInterface::class),
            new RdcEventDispatcher(new NullLogger()),
            new NullLogger(),
            true,
        );
    }

    public function testReportsACourierIncidentOnTheServiceAndItsActivity(): void
    {
        $this->incident->setFailureReasonCode('WEATHER');

        $posted = [];
        $this->rdcClient->method('post')->willReturnCallback(function (string $url, array $payload) use (&$posted) {
            $posted[$url] = $payload;

            return $this->response(201);
        });

        ($this->handler)(new IncidentRdcMessage(1));

        $this->assertSame(['/services/10/events', '/activities/20.transport/events'], array_keys($posted));
        $this->assertSame('NOT_DELIVERED', $posted['/services/10/events']['code']);
        $this->assertSame('WEATHER', $posted['/services/10/events']['reasonCode']);
        $this->assertSame('ISSUE', $posted['/services/10/events']['domain']);
    }

    public static function internalReasonCodes(): array
    {
        return [
            'price review' => ['PRICE_REVIEW_NEEDED'],
            'address review' => ['ADDRESS_REVIEW_NEEDED'],
        ];
    }

    /**
     * @dataProvider internalReasonCodes
     */
    public function testKeepsTheBackOfficeReviewsFromTheRequestor(string $reasonCode): void
    {
        // A review isn't a failed delivery: the requestor would read it as one
        $this->incident->setFailureReasonCode($reasonCode);
        $this->rdcClient->expects($this->never())->method('post');

        ($this->handler)(new IncidentRdcMessage(1));
    }

    private function setId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setValue($entity, $id);
    }

    private function response(int $statusCode): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($statusCode);

        return $response;
    }
}
