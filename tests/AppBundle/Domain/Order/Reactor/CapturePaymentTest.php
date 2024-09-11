<?php

namespace Tests\AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event\OrderCancelled;
use AppBundle\Domain\Order\Event\OrderFulfilled;
use AppBundle\Domain\Order\Reactor\CapturePayment;
use AppBundle\Entity\Sylius\Payment;
use AppBundle\Payment\Gateway;
use AppBundle\Payment\GatewayResolver;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Service\StripeManager;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Argument;
use Psr\Log\NullLogger;
use Sylius\Component\Payment\Model\PaymentInterface;
use Stripe;

class CapturePaymentTest extends TestCase
{
    use ProphecyTrait;

    private $capturePayment;

    public function setUp(): void
    {
        $this->stripeManager = $this->prophesize(StripeManager::class);

        $this->gatewayResolver = $this->prophesize(GatewayResolver::class);

        $this->stripeGateway = new Gateway\Stripe($this->stripeManager->reveal());

        $this->gateway = new Gateway(
            $this->gatewayResolver->reveal(),
            ['stripe' => $this->stripeGateway]
        );

        $this->capturePayment = new CapturePayment(
            $this->gateway,
            new NullLogger()
        );
    }

    public function testDoesNothingWhenChargeIsAlreadyCaptured()
    {
        $payment = new Payment();
        $payment->setAmount(3350);
        $payment->setCurrencyCode('EUR');
        $payment->setPaymentMethodTypes(['giropay']);

        $order = $this->prophesize(OrderInterface::class);

        $order
            ->hasVendor()
            ->willReturn(true);
        $order
            ->isFree()
            ->willReturn(true);
        $order
            ->getPayments()
            ->willReturn(new ArrayCollection([$payment]));
        $order
            ->getNumber()
            ->willReturn('ABC123');

        $payment->setOrder($order->reveal());

        $this->stripeManager
            ->capture(Argument::type(PaymentInterface::class))
            ->shouldNotBeCalled();

        call_user_func_array($this->capturePayment, [ new OrderFulfilled($order->reveal()) ]);
    }

    public function testCapturesPaymentForFulfilledOrders()
    {
        $payment = new Payment();
        $payment->setAmount(3350);
        $payment->setCurrencyCode('EUR');
        $payment->setState(PaymentInterface::STATE_AUTHORIZED);

        $order = $this->prophesize(OrderInterface::class);

        $order
            ->hasVendor()
            ->willReturn(true);
        $order
            ->isEmpty()
            ->willReturn(false);
        $order
            ->getItemsTotal()
            ->willReturn(3000);
        $order
            ->getTotal()
            ->willReturn(3350);
        $order
            ->getPayments()
            ->willReturn(new ArrayCollection([$payment]));
        $order
            ->getNumber()
            ->willReturn('ABC123');

        $payment->setOrder($order->reveal());

        $this->stripeManager
            ->capture($payment)
            ->shouldBeCalled();

        call_user_func_array($this->capturePayment, [ new OrderFulfilled($order->reveal()) ]);
    }

    public function testDoesNothingForCancelledOrders()
    {
        $payment = new Payment();
        $payment->setAmount(3350);
        $payment->setCurrencyCode('EUR');
        $payment->setState(PaymentInterface::STATE_AUTHORIZED);

        $order = $this->prophesize(OrderInterface::class);

        $order
            ->hasVendor()
            ->willReturn(true);
        $order
            ->isEmpty()
            ->willReturn(false);
        $order
            ->getItemsTotal()
            ->willReturn(3000);
        $order
            ->getTotal()
            ->willReturn(3350);
        $order
            ->getPayments()
            ->willReturn(new ArrayCollection([$payment]));
        $order
            ->getNumber()
            ->willReturn('ABC123');

        $payment->setOrder($order->reveal());

        $this->stripeManager
            ->capture($payment)
            ->shouldNotBeCalled();

        call_user_func_array($this->capturePayment, [ new OrderCancelled($order->reveal()) ]);
    }

    public function testCapturesPaymentForCancelledOrdersWithNoShowReason()
    {
        $payment = new Payment();
        $payment->setAmount(3350);
        $payment->setCurrencyCode('EUR');
        $payment->setState(PaymentInterface::STATE_AUTHORIZED);

        $order = $this->prophesize(OrderInterface::class);

        $order
            ->hasVendor()
            ->willReturn(true);
        $order
            ->isEmpty()
            ->willReturn(false);
        $order
            ->getItemsTotal()
            ->willReturn(3000);
        $order
            ->getTotal()
            ->willReturn(3350);
        $order
            ->getPayments()
            ->willReturn(new ArrayCollection([$payment]));
        $order
            ->getNumber()
            ->willReturn('ABC123');

        $payment->setOrder($order->reveal());

        $this->stripeManager
            ->capture($payment)
            ->shouldBeCalled();

        call_user_func_array($this->capturePayment, [ new OrderCancelled($order->reveal(), OrderInterface::CANCEL_REASON_NO_SHOW) ]);
    }
}
