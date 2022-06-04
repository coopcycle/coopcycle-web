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

class CancelOrderHandlerTest extends TestCase
{
    use ProphecyTrait;
    use StripeTrait {
        setUp as setUpStripe;
    }

    private $eventRecorder;
    private $stripeManager;
    private $handler;

    public function setUp(): void
    {
        $this->setUpStripe();

        $this->eventRecorder = $this->prophesize(RecordsMessages::class);
        $this->stripeManager = $this->prophesize(StripeManager::class);

        $this->handler = new CancelOrderHandler(
            $this->stripeManager->reveal(),
            $this->eventRecorder->reveal()
        );
    }

    public function testCancelOrderWithNoShowReasonThrowsException()
    {
        $this->expectException(OrderNotCancellableException::class);

        $order = new Order();
        $order->setFulfillmentMethod('delivery');

        $this->eventRecorder
            ->record(Argument::type(OrderCancelled::class))
            ->shouldNotBeCalled();

        $command = new CancelOrder($order, OrderInterface::CANCEL_REASON_NO_SHOW);

        call_user_func_array($this->handler, [ $command ]);
    }

    public function testCancelOrderWithGiropayRefundsCustomer()
    {
        $payment = new Payment();
        $payment->setAmount(3000);
        $payment->setState(PaymentInterface::STATE_COMPLETED);
        $payment->setCurrencyCode('EUR');
        $payment->setPaymentMethodTypes(['giropay']);

        $order = new Order();
        $order->addPayment($payment);
        $order->setTakeaway(true);

        $this->stripeManager
            ->refund($payment, null, true)
            ->shouldBeCalled();

        $this->eventRecorder
            ->record(Argument::type(OrderCancelled::class))
            ->shouldBeCalled();

        $command = new CancelOrder($order, OrderInterface::CANCEL_REASON_NO_SHOW);

        call_user_func_array($this->handler, [ $command ]);
    }
}
