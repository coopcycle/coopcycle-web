<?php

namespace Tests\AppBundle\MessageHandler\Order;

use AppBundle\LoopEat\Client as LoopEatClient;
use AppBundle\MessageHandler\Order\CreateLoopeatOrder;
use AppBundle\Domain\Order\Event\OrderCreated;
use AppBundle\Sylius\Order\OrderInterface;
use GuzzleHttp\Exception\RequestException;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Sylius\Component\Order\Processor\OrderProcessorInterface;

class CreateLoopeatOrderTest extends TestCase
{
    use ProphecyTrait;

    private CreateLoopeatOrder $createLoopeatOrder;
    private $loopeatClient;
    private $orderProcessor;

    public function setUp(): void
    {
        $this->loopeatClient = $this->prophesize(LoopEatClient::class);
        $this->orderProcessor = $this->prophesize(OrderProcessorInterface::class);

        $this->createLoopeatOrder = new CreateLoopeatOrder(
            $this->loopeatClient->reveal(),
            $this->orderProcessor->reveal()
        );
    }

    public function testRequestException()
    {
        $order = $this->prophesize(OrderInterface::class);
        $order->isLoopeat()->willReturn(true);

        $exception = $this->prophesize(RequestException::class);

        $this->loopeatClient->createOrder($order->reveal())->willThrow($exception->reveal());

        $event = new OrderCreated($order->reveal());

        ($this->createLoopeatOrder)($event);

        $order->setReusablePackagingEnabled(false)->shouldHaveBeenCalled();
        $order->setLoopeatReturns([])->shouldHaveBeenCalled();
        $this->orderProcessor->process($order->reveal())->shouldHaveBeenCalled();

        $order->setLoopeatOrderId(Argument::type('string'))->shouldNotHaveBeenCalled();
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
