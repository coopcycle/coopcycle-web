<?php

namespace Tests\AppBundle\MessageHandler\Order\Command;

use AppBundle\Domain\Order\Event;
use AppBundle\Message\Order\Command\CancelOrder;
use AppBundle\Domain\Order\Event\OrderCancelled;
use AppBundle\MessageHandler\Order\Command\CancelOrderHandler;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Exception\OrderNotCancellableException;
use AppBundle\Sylius\Order\OrderInterface;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Tests\AppBundle\StripeTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use SM\Factory\FactoryInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class CancelOrderHandlerTest extends KernelTestCase
{
    use ProphecyTrait;
    use StripeTrait {
        setUp as setUpStripe;
    }

    private $eventBus;
    private $stripeManager;
    private $handler;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->setUpStripe();

        $this->eventBus = $this->prophesize(MessageBusInterface::class);

        // @see https://symfony.com/blog/new-in-symfony-4-1-simpler-service-testing
        $this->stateMachineFactory = self::getContainer()->get(FactoryInterface::class);

        $this->handler = new CancelOrderHandler(
            $this->eventBus->reveal(),
            $this->stateMachineFactory
        );
    }

    public function testCancelOrderWithNoShowReasonThrowsException()
    {
        $this->expectException(OrderNotCancellableException::class);

        $order = new Order();
        $order->setState(OrderInterface::STATE_NEW);
        $order->setFulfillmentMethod('delivery');

        $this->eventBus
            ->dispatch(Argument::type(OrderCancelled::class))
            ->shouldNotBeCalled();

        $command = new CancelOrder($order, OrderInterface::CANCEL_REASON_NO_SHOW);

        call_user_func_array($this->handler, [ $command ]);
    }

    public function testCancelAcceptedOrder()
    {
        $order = new Order();
        $order->setState(OrderInterface::STATE_ACCEPTED);

        $this->eventBus
            ->dispatch(Argument::that(function(Envelope $envelope){
                $this->assertInstanceOf(OrderCancelled::class, $envelope->getMessage());
                return true;
            }))
            ->willReturn(new Envelope(new OrderCancelled($order)))
            ->shouldBeCalledOnce();
        $command = new CancelOrder($order);

        call_user_func_array($this->handler, [ $command ]);
    }

    public function testCancelFulfilledOrderThrowsException()
    {
        $this->expectException(OrderNotCancellableException::class);
        $this->expectExceptionMessage('Order #0 cannot be cancelled');

        $order = new Order();
        $order->setState(OrderInterface::STATE_FULFILLED);

        $this->eventBus
            ->dispatch(Argument::type(OrderCancelled::class))
            ->shouldNotBeCalled();

        $command = new CancelOrder($order);

        call_user_func_array($this->handler, [ $command ]);
    }
}
