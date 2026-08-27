<?php

namespace Tests\AppBundle\MessageHandler\Zelty;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\OrderRepository;
use AppBundle\Integration\Zelty\ZeltyClient;
use AppBundle\Message\Zelty\PushOrder;
use AppBundle\MessageHandler\Zelty\PushOrderHandler;
use AppBundle\Entity\Sylius\Order;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class PushOrderHandlerTest extends TestCase
{
    use ProphecyTrait;

    private $zeltyClient;
    private $orderRepository;
    private PushOrderHandler $handler;

    public function setUp(): void
    {
        $this->zeltyClient = $this->prophesize(ZeltyClient::class);
        $this->orderRepository = $this->prophesize(OrderRepository::class);

        $this->handler = new PushOrderHandler(
            $this->zeltyClient->reveal(),
            $this->orderRepository->reveal(),
            $this->prophesize(EntityManagerInterface::class)->reveal()
        );
    }

    private function givenOrder(LocalBusiness $restaurant): void
    {
        $order = $this->prophesize(Order::class);
        $order->getRestaurant()->willReturn($restaurant);
        $order->getItemsTotal()->willReturn(1000);
        $order->getAdjustmentsTotal(\Prophecy\Argument::any())->willReturn(250);
        $order->setZeltyOrderId(\Prophecy\Argument::any())->shouldBeCalled();

        $this->orderRepository->find(1)->willReturn($order->reveal());
        $this->zeltyClient->setRestaurant($restaurant)->shouldBeCalled();
        $this->zeltyClient->pushToZelty($order->reveal())->willReturn(4242);
    }

    public function testPaymentIsRecordedUnderTheDefaultMethod()
    {
        $restaurant = new LocalBusiness();
        $restaurant->setZeltyOrdersEnabled(true);

        $this->givenOrder($restaurant);

        $this->zeltyClient->addTransaction(4242, 1250, 'CB')->shouldBeCalledTimes(1);

        $this->handler->__invoke(new PushOrder(1));
    }

    public function testPaymentIsRecordedUnderTheConfiguredMethod()
    {
        $restaurant = new LocalBusiness();
        $restaurant->setZeltyOrdersEnabled(true);
        $restaurant->setZeltyTransactionMethod('Ticket restaurant');

        $this->givenOrder($restaurant);

        $this->zeltyClient->addTransaction(4242, 1250, 'Ticket restaurant')->shouldBeCalledTimes(1);

        $this->handler->__invoke(new PushOrder(1));
    }

    public function testAnEmptyMethodFallsBackToTheDefault()
    {
        $restaurant = new LocalBusiness();
        $restaurant->setZeltyOrdersEnabled(true);
        $restaurant->setZeltyTransactionMethod('');

        $this->givenOrder($restaurant);

        $this->zeltyClient->addTransaction(4242, 1250, 'CB')->shouldBeCalledTimes(1);

        $this->handler->__invoke(new PushOrder(1));
    }
}
