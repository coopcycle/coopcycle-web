<?php

namespace Tests\AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event;
use AppBundle\Domain\Order\Reactor\UpdateState;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\Payment;
use AppBundle\Entity\Task;
use AppBundle\Sylius\Order\OrderInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use SimpleBus\Message\Bus\MessageBus;
use SM\Factory\FactoryInterface;
use SM\StateMachine\StateMachineInterface;
use SM\SMException;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class UpdateStateTest extends KernelTestCase
{
    private $orderProcessor;
    private $serializer;
    private $eventBus;

    private $updateState;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        // @see https://symfony.com/blog/new-in-symfony-4-1-simpler-service-testing
        $this->stateMachineFactory = self::$container->get(FactoryInterface::class);

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

        $this->assertEquals(OrderInterface::STATE_NEW, $order->getState());
        $this->assertEquals(PaymentInterface::STATE_AUTHORIZED, $payment->getState());
    }

    public function testCheckoutFailed()
    {
        $order = new Order();
        $payment = new Payment();

        $this->orderProcessor
            ->process(Argument::is($order))
            ->shouldBeCalled();

        call_user_func_array($this->updateState, [ new Event\CheckoutFailed($order, $payment, 'Lorem ipsum') ]);

        // $this->stateMachine->apply('fail')->shouldHaveBeenCalled();
    }

    public function testOrderCreated()
    {
        $order = new Order();
        $order->setState(OrderInterface::STATE_CART);

        call_user_func_array($this->updateState, [ new Event\OrderCreated($order) ]);

        $this->assertEquals(OrderInterface::STATE_NEW, $order->getState());
    }

    public function testOrderCreatedWithStateNew()
    {
        $order = new Order();
        $order->setState(OrderInterface::STATE_NEW);

        call_user_func_array($this->updateState, [ new Event\OrderCreated($order) ]);

        $this->assertEquals(OrderInterface::STATE_NEW, $order->getState());
    }

    public function testOrderAccepted()
    {
        $order = new Order();
        $order->setState(OrderInterface::STATE_NEW);

        call_user_func_array($this->updateState, [ new Event\OrderAccepted($order) ]);

        $this->assertEquals(OrderInterface::STATE_ACCEPTED, $order->getState());
    }

    public function testFulfillOrderWithCompletedTasks()
    {
        $restaurant = new Restaurant();

        $order = new Order();
        $order->setState(OrderInterface::STATE_ACCEPTED);
        $order->setRestaurant($restaurant);

        $delivery = new Delivery();
        $delivery->getPickup()->setStatus(Task::STATUS_DONE);
        $delivery->getDropoff()->setStatus(Task::STATUS_DONE);

        $order->setDelivery($delivery);

        $sm = $this->stateMachineFactory->get($order, \AppBundle\Sylius\Order\OrderTransitions::GRAPH);

        call_user_func_array($this->updateState, [ new Event\OrderFulfilled($order) ]);

        $this->assertEquals(OrderInterface::STATE_FULFILLED, $order->getState());
    }

    public function testFulfillOrderWithUncompletedTasks()
    {
        $restaurant = new Restaurant();

        $order = new Order();
        $order->setState(OrderInterface::STATE_ACCEPTED);
        $order->setRestaurant($restaurant);

        $delivery = new Delivery();
        $delivery->getPickup()->setStatus(Task::STATUS_DONE);
        $delivery->getDropoff()->setStatus(Task::STATUS_TODO);

        $order->setDelivery($delivery);

        $sm = $this->stateMachineFactory->get($order, \AppBundle\Sylius\Order\OrderTransitions::GRAPH);

        call_user_func_array($this->updateState, [ new Event\OrderFulfilled($order) ]);

        $this->assertEquals(OrderInterface::STATE_ACCEPTED, $order->getState());
    }
}
