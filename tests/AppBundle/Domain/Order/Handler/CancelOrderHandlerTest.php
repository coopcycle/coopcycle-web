<?php

namespace Tests\AppBundle\Domain\Order\Handler;

use AppBundle\Domain\Order\Command\CancelOrder;
use AppBundle\Domain\Order\Event\OrderCancelled;
use AppBundle\Domain\Order\Handler\CancelOrderHandler;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\Payment;
use AppBundle\Service\StripeManager;
use AppBundle\Exception\OrderNotCancellableException;
use AppBundle\Sylius\Order\OrderInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Stripe;
use Sylius\Component\Payment\Model\PaymentInterface;
use SimpleBus\Message\Recorder\RecordsMessages;
use Tests\AppBundle\StripeTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use SM\Factory\FactoryInterface;

class CancelOrderHandlerTest extends KernelTestCase
{
    use ProphecyTrait;
    use StripeTrait {
        setUp as setUpStripe;
    }

    private $eventRecorder;
    private $stripeManager;
    private $handler;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->setUpStripe();

        $this->eventRecorder = $this->prophesize(RecordsMessages::class);
        $this->stripeManager = $this->prophesize(StripeManager::class);

        // @see https://symfony.com/blog/new-in-symfony-4-1-simpler-service-testing
        $this->stateMachineFactory = self::$container->get(FactoryInterface::class);

        $this->handler = new CancelOrderHandler(
            $this->stripeManager->reveal(),
            $this->eventRecorder->reveal(),
            $this->stateMachineFactory
        );
    }

    public function testCancelOrderWithNoShowReasonThrowsException()
    {
        $this->expectException(OrderNotCancellableException::class);

        $order = new Order();
        $order->setState(OrderInterface::STATE_NEW);
        $order->setFulfillmentMethod('delivery');

        $this->eventRecorder
            ->record(Argument::type(OrderCancelled::class))
            ->shouldNotBeCalled();

        $command = new CancelOrder($order, OrderInterface::CANCEL_REASON_NO_SHOW);

        call_user_func_array($this->handler, [ $command ]);
    }

    public function testCancelAcceptedOrder()
    {
        $order = new Order();
        $order->setState(OrderInterface::STATE_ACCEPTED);

        $this->eventRecorder
            ->record(Argument::type(OrderCancelled::class))
            ->shouldBeCalled();

        $command = new CancelOrder($order);

        call_user_func_array($this->handler, [ $command ]);
    }

    public function testCancelFulfilledOrderThrowsException()
    {
        $this->expectException(OrderNotCancellableException::class);
        $this->expectExceptionMessage('Order #0 cannot be cancelled');

        $order = new Order();
        $order->setState(OrderInterface::STATE_FULFILLED);

        $this->eventRecorder
            ->record(Argument::type(OrderCancelled::class))
            ->shouldNotBeCalled();

        $command = new CancelOrder($order);

        call_user_func_array($this->handler, [ $command ]);
    }
}
