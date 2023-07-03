<?php

namespace Tests\AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event\OrderCreated;
use AppBundle\Domain\Order\Reactor\CreateLoopeatOrder;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Vendor;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\OrderTextEncoder;
use AppBundle\LoopEat\Client as LoopEatClient;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Argument;

class CreateLoopeatOrderTest extends TestCase
{
    use ProphecyTrait;

    private $createLoopeatOrder;

    public function setUp(): void
    {
        $this->loopeatClient = $this->prophesize(LoopEatClient::class);

        $this->createLoopeatOrder = new CreateLoopeatOrder(
            $this->loopeatClient->reveal()
        );
    }

    public function testDoesNothing()
    {
        $order = $this->prophesize(OrderInterface::class);

        $order
            ->isLoopeat()
            ->willReturn(false);

        $this->loopeatClient
            ->createOrder($order)
            ->willReturn(['id' => 123456])
            ->shouldNotBeCalled();

        call_user_func_array($this->createLoopeatOrder, [ new OrderCreated($order->reveal()) ]);
    }

    public function testCreatesOrder()
    {
        $order = $this->prophesize(OrderInterface::class);

        $order
            ->isLoopeat()
            ->willReturn(true);

        $this->loopeatClient
            ->createOrder($order)
            ->willReturn(['id' => 123456])
            ->shouldBeCalled();

        call_user_func_array($this->createLoopeatOrder, [ new OrderCreated($order->reveal()) ]);

        $order
            ->setLoopeatOrderId(123456)
            ->shouldHaveBeenCalled();
    }
}

