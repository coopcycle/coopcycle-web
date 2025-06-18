<?php

namespace Tests\AppBundle\MessageHandler\Order;

use AppBundle\DataType\TsRange;
use AppBundle\Domain\Order\Event;
use AppBundle\Domain\Order\Event\OrderAccepted;
use AppBundle\Domain\Order\Event\OrderCancelled;
use AppBundle\Domain\Order\Event\OrderCreated;
use AppBundle\Domain\Order\Event\OrderFulfilled;
use AppBundle\Domain\Order\Event\OrderStateChanged;
use AppBundle\MessageHandler\Order\UpdateState;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\Payment;
use AppBundle\Entity\Task;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\OrderTimeHelper;
use Doctrine\ORM\EntityManagerInterface;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use SM\Factory\FactoryInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class UpdateStateTest extends KernelTestCase
{
    use ProphecyTrait;

    private $orderProcessor;
    private $serializer;
    private $eventBus;

    private $updateState;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        // @see https://symfony.com/blog/new-in-symfony-4-1-simpler-service-testing
        $this->stateMachineFactory = self::getContainer()->get(FactoryInterface::class);

        $this->orderProcessor = $this->prophesize(OrderProcessorInterface::class);
        $this->eventBus = $this->prophesize(MessageBusInterface::class);

        $this->orderTimeHelper = $this->prophesize(OrderTimeHelper::class);

        $this->shippingTimeRange = new TsRange();
        $this->shippingTimeRange->setLower(new \DateTime('2020-04-09 19:55:00'));
        $this->shippingTimeRange->setUpper(new \DateTime('2020-04-09 20:05:00'));

        $this->orderTimeHelper
            ->getShippingTimeRange(Argument::type(OrderInterface::class))
            ->willReturn($this->shippingTimeRange);

        $this->entityManager = $this->prophesize(EntityManagerInterface::class);

        $this->updateState = new UpdateState(
            $this->stateMachineFactory,
            $this->orderProcessor->reveal(),
            $this->eventBus->reveal(),
            $this->orderTimeHelper->reveal(),
            $this->entityManager->reveal()
        );
    }

    public function testCheckoutSucceeded()
    {
        $order = new Order();
        $payment = new Payment();

        $this->eventBus
            ->dispatch(Argument::that(function (Event\OrderCreated $event) use ($order) {
                return $event->getOrder() === $order;
            }))
            ->shouldBeCalledOnce()
            ->willReturn(new Envelope(new Event\OrderCreated($order)));

        call_user_func_array($this->updateState, [ new Event\CheckoutSucceeded($order, $payment) ]);

        $this->assertEquals(OrderInterface::STATE_NEW, $order->getState());
        $this->assertEquals(PaymentInterface::STATE_AUTHORIZED, $payment->getState());

        $this->assertNotNull($order->getShippingTimeRange());
        $this->assertEquals($this->shippingTimeRange, $order->getShippingTimeRange());
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

        $this->eventBus
            ->dispatch(Argument::that(function (Envelope $message) {
                $event = $message->getMessage();
                $this->assertInstanceOf(OrderCreated::class, $event->getTriggeredBy());
                return true;
            }))
            ->shouldBeCalledOnce()
            ->willReturn(new Envelope(new Event\OrderStateChanged($order, new Event\OrderCreated($order))));


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

        $this->eventBus
            ->dispatch(Argument::that(function (Envelope $message) {
                $event = $message->getMessage();
                $this->assertInstanceOf(OrderAccepted::class, $event->getTriggeredBy());
                return true;
            }))
            ->shouldBeCalledOnce()
            ->willReturn(new Envelope(new Event\OrderStateChanged($order, new Event\OrderAccepted($order))));


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

        $this->eventBus
            ->dispatch(Argument::that(function (Envelope $message) {
                $event = $message->getMessage();
                $this->assertInstanceOf(OrderFulfilled::class, $event->getTriggeredBy());
                return true;
            }))
            ->shouldBeCalledOnce()
            ->willReturn(new Envelope(new Event\OrderStateChanged($order, new Event\OrderFulfilled($order))));


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

    public function testOrderCancelledWithReasonNoShow()
    {
        $order = new Order();
        $order->setState(OrderInterface::STATE_ACCEPTED);

        $payment = new Payment();
        $payment->setState(PaymentInterface::STATE_AUTHORIZED);
        $order->addPayment($payment);

        $this->eventBus
            ->dispatch(Argument::that(function (Envelope $message) {
                $event = $message->getMessage();
                $this->assertInstanceOf(OrderCancelled::class, $event->getTriggeredBy());
                return true;
            }))
            ->shouldBeCalledOnce()
            ->willReturn(new Envelope(new Event\OrderStateChanged($order, new Event\OrderCancelled($order))));

        call_user_func_array($this->updateState, [ new Event\OrderCancelled($order, OrderInterface::CANCEL_REASON_NO_SHOW) ]);

        $this->assertEquals(OrderInterface::STATE_CANCELLED, $order->getState());
        $this->assertNotEquals(PaymentInterface::STATE_CANCELLED, $payment->getState());
    }
}
