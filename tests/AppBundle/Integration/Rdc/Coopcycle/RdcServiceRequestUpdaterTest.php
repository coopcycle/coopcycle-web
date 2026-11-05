<?php

declare(strict_types=1);

namespace Tests\AppBundle\Integration\Rdc\Coopcycle;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\DeliveryRepository;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Exception\OrderNotCancellableException;
use AppBundle\Integration\Rdc\Coopcycle\RdcServiceRequestUpdater;
use AppBundle\Integration\Rdc\DTO\RdcApiServiceRequest;
use AppBundle\Integration\Rdc\Enum\ServiceStatus;
use AppBundle\Service\OrderManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sylius\Component\Order\Model\OrderInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

class RdcServiceRequestUpdaterTest extends TestCase
{
    private const LO_URI = 'https://bol-api.requestor.example.com/service-requests/sr-1';

    private DeliveryRepository&MockObject $deliveryRepository;
    private OrderManager&MockObject $orderManager;
    private RdcServiceRequestUpdater $updater;

    protected function setUp(): void
    {
        $this->deliveryRepository = $this->createMock(DeliveryRepository::class);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->with(Delivery::class)->willReturn($this->deliveryRepository);

        $this->orderManager = $this->createMock(OrderManager::class);

        $this->updater = new RdcServiceRequestUpdater($entityManager, $this->orderManager, new NullLogger());
    }

    public function testCancelsTheDeliveryOfACancelledServiceRequest(): void
    {
        $delivery = $this->deliveryWithOrder(OrderInterface::STATE_NEW);
        $this->deliveryRepository->method('findByLoUri')->with(self::LO_URI)->willReturn($delivery);

        $this->orderManager->expects($this->once())->method('cancel')->with($delivery->getOrder());

        $this->updater->update($this->serviceRequest(ServiceStatus::CANCELLED), self::LO_URI);

        // So that the cancellation isn't sent back to the requestor
        $this->assertTrue($delivery->getMetadata()['rdc']['cancelled_by_requestor']);
    }

    public function testIgnoresTheOtherUpdates(): void
    {
        $this->deliveryRepository->expects($this->never())->method('findByLoUri');
        $this->orderManager->expects($this->never())->method('cancel');

        $this->updater->update($this->serviceRequest(null), self::LO_URI);
    }

    public function testIgnoresAServiceRequestThatNeverBecameADelivery(): void
    {
        $this->deliveryRepository->method('findByLoUri')->willReturn(null);
        $this->orderManager->expects($this->never())->method('cancel');

        $this->updater->update($this->serviceRequest(ServiceStatus::CANCELLED), self::LO_URI);
    }

    public function testLeavesAnAlreadyCancelledDeliveryAlone(): void
    {
        $this->deliveryRepository->method('findByLoUri')->willReturn($this->deliveryWithOrder(OrderInterface::STATE_CANCELLED));
        $this->orderManager->expects($this->never())->method('cancel');

        $this->updater->update($this->serviceRequest(ServiceStatus::CANCELLED), self::LO_URI);
    }

    public function testADeliveryThatCanNoLongerBeCancelledIsOnlyLogged(): void
    {
        $delivery = $this->deliveryWithOrder(OrderInterface::STATE_FULFILLED);
        $this->deliveryRepository->method('findByLoUri')->willReturn($delivery);

        $this->orderManager->method('cancel')->willThrowException(new HandlerFailedException(
            new Envelope(new \stdClass()),
            [new OrderNotCancellableException('Order #1 cannot be cancelled')]
        ));

        $this->updater->update($this->serviceRequest(ServiceStatus::CANCELLED), self::LO_URI);

        $this->assertArrayNotHasKey('cancelled_by_requestor', $delivery->getMetadata()['rdc'] ?? []);
    }

    private function serviceRequest(?ServiceStatus $requestStatus): RdcApiServiceRequest
    {
        return new RdcApiServiceRequest(requestStatus: $requestStatus);
    }

    private function deliveryWithOrder(string $state): Delivery
    {
        $order = new Order();
        $order->setState($state);

        $delivery = new Delivery();
        $delivery->setOrder($order);

        return $delivery;
    }
}
