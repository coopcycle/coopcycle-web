<?php

namespace Tests\AppBundle\MessageHandler\Order;

use AppBundle\Domain\Order\Event\OrderCreated;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\OrderItem;
use AppBundle\Entity\Sylius\Product;
use AppBundle\Entity\Sylius\ProductVariant;
use AppBundle\Message\Zelty\PushOrder;
use AppBundle\MessageHandler\Order\DispatchZeltyPushOrder;
use AppBundle\Sylius\Order\OrderInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Doctrine\Common\Collections\ArrayCollection;
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

    /**
     * @param array<?string> $zeltyInternalIds one entry per order item, NULL for a
     *                                         product that does not come from Zelty
     */
    private function event(bool $zeltyOrdersEnabled, array $zeltyInternalIds = ['1980']): OrderCreated
    {
        $restaurant = $this->createMock(LocalBusiness::class);
        $restaurant->method('getId')->willReturn(1);
        $restaurant->method('getZeltyApiKey')->willReturn('an-api-key');
        $restaurant->method('isZeltyOrdersEnabled')->willReturn($zeltyOrdersEnabled);

        $items = array_map(fn(?string $internalId) => $this->orderItem($internalId), $zeltyInternalIds);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getId')->willReturn(42);
        $order->method('getRestaurant')->willReturn($restaurant);
        $order->method('getItems')->willReturn(new ArrayCollection($items));

        return new OrderCreated($order);
    }

    private function orderItem(?string $zeltyInternalId): OrderItem
    {
        $product = $this->createMock(Product::class);
        $product->method('getZeltyInternalId')->willReturn($zeltyInternalId);

        $variant = $this->createMock(ProductVariant::class);
        $variant->method('getProduct')->willReturn($product);

        $item = $this->createMock(OrderItem::class);
        $item->method('getVariant')->willReturn($variant);

        return $item;
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

    public function testDoesNotPushOrderContainingAProductNotImportedFromZelty(): void
    {
        $this->commandBus->expects($this->never())->method('dispatch');

        // Zelty would reject the whole order with "Cannot find dish"
        $this->handler->__invoke($this->event(true, ['1980', null]));
    }

    public function testDoesNotPushAnOrderWithoutItems(): void
    {
        $this->commandBus->expects($this->never())->method('dispatch');

        $this->handler->__invoke($this->event(true, []));
    }

    public function testPushesOrderWhenEveryItemComesFromZelty(): void
    {
        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(PushOrder::class))
            ->willReturnCallback(fn($message) => new Envelope($message));

        $this->handler->__invoke($this->event(true, ['1980', '1983']));
    }
}
