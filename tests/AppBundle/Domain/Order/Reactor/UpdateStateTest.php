<?php

namespace Tests\AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event;
use AppBundle\Domain\Order\Reactor\UpdateState;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\Payment;
use AppBundle\Sylius\Order\OrderInterface;
use Prophecy\Argument;
use SimpleBus\Message\Bus\MessageBus;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;

class UpdateStateTest extends KernelTestCase
{
    private $stateMachineFactory;
    private $orderProcessor;
    private $serializer;
    private $eventBus;

    private $updateState;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->stateMachineFactory = static::$kernel->getContainer()->get('sm.factory');
        $this->orderProcessor = $this->prophesize(OrderProcessorInterface::class);
        $this->serializer = $this->prophesize(SerializerInterface::class);
        $this->eventBus = $this->prophesize(MessageBus::class);

        $this->serializer
            ->serialize(Argument::type(OrderInterface::class), 'json', Argument::type('array'))
            ->willReturn(json_encode(['foo' => 'bar']));

        $this->updateState = new UpdateState(
            $this->stateMachineFactory,
            $this->orderProcessor->reveal(),
            $this->serializer->reveal(),
            $this->eventBus->reveal()
        );
        }

    public function testCheckoutSucceeded()
    {
        $order = new Order();
        $payment = new Payment();

        $this->eventBus
            ->handle(Argument::that(function (Event\OrderCreated $event) use ($order) {
                return $event->getOrder() === $order;
            }))
            ->shouldBeCalled();

        call_user_func_array($this->updateState, [ new Event\CheckoutSucceeded($order, $payment) ]);

        $this->assertEquals('authorized', $payment->getState());
    }

    public function testCheckoutFailed()
    {
        $order = new Order();
        $payment = new Payment();

        $this->orderProcessor
            ->process(Argument::is($order))
            ->shouldBeCalled();

        call_user_func_array($this->updateState, [ new Event\CheckoutFailed($order, $payment, 'Lorem ipsum') ]);

        $this->assertEquals('failed', $payment->getState());
        $this->assertEquals('Lorem ipsum', $payment->getLastError());
    }

    public function testOrderCreated()
    {
        $order = new Order();
        $order->setState(OrderInterface::STATE_CART);

        call_user_func_array($this->updateState, [ new Event\OrderCreated($order) ]);

        $this->assertEquals('new', $order->getState());
    }

    public function testOrderAccepted()
    {
        $order = new Order();
        $order->setState(OrderInterface::STATE_NEW);

        call_user_func_array($this->updateState, [ new Event\OrderAccepted($order) ]);

        $this->assertEquals('accepted', $order->getState());
    }
}
