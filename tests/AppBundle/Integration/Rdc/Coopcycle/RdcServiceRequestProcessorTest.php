<?php

declare(strict_types=1);

namespace Tests\AppBundle\Integration\Rdc\Coopcycle;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\DeliveryRepository;
use AppBundle\Entity\Store;
use AppBundle\Integration\Rdc\Api\RdcClientInterface;
use AppBundle\Integration\Rdc\Api\RdcServiceFacade;
use AppBundle\Integration\Rdc\Coopcycle\RdcServiceRequestMapper;
use AppBundle\Integration\Rdc\Coopcycle\RdcServiceRequestProcessor;
use AppBundle\Integration\Rdc\DTO\RdcApiServiceRequest;
use AppBundle\Service\DeliveryOrderManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class RdcServiceRequestProcessorTest extends TestCase
{
    private const LO_URI = 'https://bol-api.example.com/service-requests/f9b10095';

    private DeliveryRepository&MockObject $deliveryRepository;
    private DeliveryOrderManager&MockObject $deliveryOrderManager;
    private RdcServiceFacade&MockObject $serviceFacade;
    private RdcServiceRequestMapper&MockObject $mapper;
    private RdcServiceRequestProcessor $processor;

    protected function setUp(): void
    {
        $this->deliveryRepository = $this->createMock(DeliveryRepository::class);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->with(Delivery::class)->willReturn($this->deliveryRepository);

        $rdcClient = $this->createMock(RdcClientInterface::class);
        $rdcClient->method('getBaseUrl')->willReturn('https://bol-api.coopcycle.example.com');

        $this->deliveryOrderManager = $this->createMock(DeliveryOrderManager::class);
        $this->serviceFacade = $this->createMock(RdcServiceFacade::class);
        $this->serviceFacade->method('getRdcClient')->willReturn($rdcClient);
        $this->mapper = $this->createMock(RdcServiceRequestMapper::class);

        $this->processor = new RdcServiceRequestProcessor(
            $entityManager,
            $this->deliveryOrderManager,
            $this->serviceFacade,
            $this->mapper,
            new NullLogger(),
        );
    }

    public function testCreatesDeliveryAndRemembersBolIds(): void
    {
        $delivery = new Delivery();

        $this->deliveryRepository->method('findByLoUri')->with(self::LO_URI)->willReturn(null);
        $this->mapper->expects($this->once())->method('mapToDelivery')->willReturn($delivery);
        $this->deliveryOrderManager->expects($this->once())->method('createOrder');
        $this->serviceFacade->expects($this->once())->method('createService')->willReturn('svc-1');
        $this->serviceFacade->expects($this->once())->method('createActivity')->willReturn('act-1');
        $this->serviceFacade->expects($this->once())->method('linkActivityToService')->with('svc-1', 'act-1');
        $this->serviceFacade->expects($this->once())->method('notifyOriginNode');

        $result = $this->processor->process(new RdcApiServiceRequest(), new Store(), self::LO_URI, 1);

        $this->assertSame($delivery, $result);
        $this->assertSame('svc-1', $delivery->getMetadata()['rdc']['service_id']);
        $this->assertSame('act-1', $delivery->getMetadata()['rdc']['activity_id']);
    }

    public function testRetryReusesDeliveryAndSkipsTheServiceAlreadyCreated(): void
    {
        $delivery = new Delivery();
        $delivery->setMetadata('rdc.service_id', 'svc-1');

        $this->deliveryRepository->method('findByLoUri')->with(self::LO_URI)->willReturn($delivery);
        $this->mapper->expects($this->never())->method('mapToDelivery');
        $this->deliveryOrderManager->expects($this->never())->method('createOrder');
        $this->serviceFacade->expects($this->never())->method('createService');
        $this->serviceFacade->expects($this->once())->method('createActivity')->willReturn('act-1');
        $this->serviceFacade->expects($this->once())->method('linkActivityToService')->with('svc-1', 'act-1');
        $this->serviceFacade->expects($this->once())->method('notifyOriginNode');

        $result = $this->processor->process(new RdcApiServiceRequest(), new Store(), self::LO_URI, 1);

        $this->assertSame($delivery, $result);
        $this->assertSame('act-1', $delivery->getMetadata()['rdc']['activity_id']);
    }

    public function testRetryOnlyLinksAndNotifiesWhenBothIdsExist(): void
    {
        $delivery = new Delivery();
        $delivery->setMetadata('rdc.service_id', 'svc-1');
        $delivery->setMetadata('rdc.activity_id', 'act-1');

        $this->deliveryRepository->method('findByLoUri')->willReturn($delivery);
        $this->serviceFacade->expects($this->never())->method('createService');
        $this->serviceFacade->expects($this->never())->method('createActivity');
        $this->serviceFacade->expects($this->once())->method('linkActivityToService')->with('svc-1', 'act-1');
        $this->serviceFacade->expects($this->once())->method('notifyOriginNode');

        $this->processor->process(new RdcApiServiceRequest(), new Store(), self::LO_URI, 1);
    }

    public function testFailureAfterServiceCreationKeepsItsIdForTheRetry(): void
    {
        $delivery = new Delivery();

        $this->deliveryRepository->method('findByLoUri')->willReturn(null);
        $this->mapper->method('mapToDelivery')->willReturn($delivery);
        $this->serviceFacade->method('createService')->willReturn('svc-1');
        $this->serviceFacade->method('createActivity')->willThrowException(new \RuntimeException('BOL is down'));

        try {
            $this->processor->process(new RdcApiServiceRequest(), new Store(), self::LO_URI, 1);
            $this->fail('The BOL failure should be rethrown, so that Messenger retries');
        } catch (\RuntimeException $e) {
            $this->assertSame('BOL is down', $e->getMessage());
        }

        $this->assertSame('svc-1', $delivery->getMetadata()['rdc']['service_id']);
        $this->assertArrayNotHasKey('activity_id', $delivery->getMetadata()['rdc']);
    }
}
