<?php

namespace Tests\AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event;
use AppBundle\Domain\Order\Reactor\UpdateState;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\Payment;
use AppBundle\Sylius\Order\OrderInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use SimpleBus\Message\Bus\MessageBus;
use SM\Factory\FactoryInterface;
use SM\StateMachine\StateMachineInterface;
use SM\SMException;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Component\Serializer\SerializerInterface;

class UpdateStateTest extends TestCase
{
    private $stateMachineFactory;
    private $orderProcessor;
    private $serializer;
    private $eventBus;

    private $updateState;

    protected function setUp(): void
    {
        $this->stateMachineFactory = $this->prophesize(FactoryInterface::class);
        $this->stateMachine = $this->prophesize(StateMachineInterface::class);

        $this->stateMachineFactory
            ->get(Argument::type('object'), Argument::type('string'))
            ->willReturn($this->stateMachine->reveal());

        $this->orderProcessor = $this->prophesize(OrderProcessorInterface::class);
        $this->serializer = $this->prophesize(SerializerInterface::class);
        $this->eventBus = $this->prophesize(MessageBus::class);

        $this->serializer
            ->serialize(Argument::type(OrderInterface::class), 'json', Argument::type('array'))
            ->willReturn(json_encode(['foo' => 'bar']));

        $this->updateState = new UpdateState(
            $this->stateMachineFactory->reveal(),
            $this->orderProcessor->reveal(),
            $this->serializer->reveal(),
            $this->eventBus->reveal()
        );
    }

    public function testCheckoutSucceeded()
    {
        $order = new Order();
        $payment = new Payment();

        $this->stateMachine
            ->can('authorize')
            ->willReturn(true);

        $this->stateMachine
            ->apply('authorize')
            ->shouldBeCalled();

        $this->eventBus
            ->handle(Argument::that(function (Event\OrderCreated $event) use ($order) {
                return $event->getOrder() === $order;
            }))
            ->shouldBeCalled();

        call_user_func_array($this->updateState, [ new Event\CheckoutSucceeded($order, $payment) ]);
    }

    public function testCheckoutFailed()
    {
        $order = new Order();
        $payment = new Payment();

        $this->orderProcessor
            ->process(Argument::is($order))
            ->shouldBeCalled();

        call_user_func_array($this->updateState, [ new Event\CheckoutFailed($order, $payment, 'Lorem ipsum') ]);

        $this->stateMachine->apply('fail')->shouldHaveBeenCalled();
    }

    public function testOrderCreated()
    {
        $order = new Order();
        $order->setState(OrderInterface::STATE_CART);

        call_user_func_array($this->updateState, [ new Event\OrderCreated($order) ]);

        $this->stateMachine->apply('create', true)->shouldHaveBeenCalled();
    }

    public function testOrderAccepted()
    {
        $order = new Order();
        $order->setState(OrderInterface::STATE_NEW);

        call_user_func_array($this->updateState, [ new Event\OrderAccepted($order) ]);

        $this->stateMachine->apply('accept', true)->shouldHaveBeenCalled();
    }

    public function testOrderFulfilled()
    {
        $order = new Order();
        $order->setState(OrderInterface::STATE_ACCEPTED);

        call_user_func_array($this->updateState, [ new Event\OrderFulfilled($order) ]);

        $this->stateMachine->apply('fulfill', true)->shouldHaveBeenCalled();
    }
}
