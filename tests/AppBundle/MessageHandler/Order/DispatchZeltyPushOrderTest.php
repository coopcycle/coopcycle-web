<?php

namespace Tests\AppBundle\MessageHandler\Order;

use AppBundle\Domain\Order\Event\OrderCreated;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Message\Zelty\PushOrder;
use AppBundle\MessageHandler\Order\DispatchZeltyPushOrder;
use AppBundle\Sylius\Order\OrderInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class DispatchZeltyPushOrderTest extends TestCase
{
    private MessageBusInterface $commandBus;
    private DispatchZeltyPushOrder $handler;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->handler = new DispatchZeltyPushOrder($this->commandBus, new NullLogger());
    }

    private function event(bool $zeltyOrdersEnabled): OrderCreated
    {
        $restaurant = $this->createMock(LocalBusiness::class);
        $restaurant->method('getId')->willReturn(1);
        $restaurant->method('getZeltyApiKey')->willReturn('an-api-key');
        $restaurant->method('isZeltyOrdersEnabled')->willReturn($zeltyOrdersEnabled);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getId')->willReturn(42);
        $order->method('getRestaurant')->willReturn($restaurant);

        return new OrderCreated($order);
    }

    public function testPushesOrderWhenZeltyOrdersAreEnabled(): void
    {
        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(PushOrder::class))
            ->willReturnCallback(fn($message) => new Envelope($message));

        $this->handler->__invoke($this->event(true));
    }

    public function testDoesNotPushOrderWhenZeltyOrdersAreDisabled(): void
    {
        $this->commandBus->expects($this->never())->method('dispatch');

        $this->handler->__invoke($this->event(false));
    }
}
