<?php

namespace Tests\AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event\OrderCancelled;
use AppBundle\Domain\Order\Event\OrderFulfilled;
use AppBundle\Domain\Order\Reactor\CapturePayment;
use AppBundle\Edenred\Client as EdenredClient;
use AppBundle\Entity\Sylius\Payment;
use AppBundle\Message\RetrieveStripeFee;
use AppBundle\Payment\Gateway;
use AppBundle\Payment\GatewayResolver;
use AppBundle\Service\MercadopagoManager;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Service\StripeManager;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Argument;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Stripe;

class CapturePaymentTest extends TestCase
{
    use ProphecyTrait;

    private $capturePayment;

    public function setUp(): void
    {
        $this->stripeManager = $this->prophesize(StripeManager::class);
        $this->mercadopagoManager = $this->prophesize(MercadopagoManager::class);
        $this->edenred = $this->prophesize(EdenredClient::class);

        $this->gatewayResolver = $this->prophesize(GatewayResolver::class);

        $this->messageBus = $this->prophesize(MessageBusInterface::class);
        $this->messageBus
            ->dispatch(Argument::type(RetrieveStripeFee::class), Argument::type('array'))
            ->will(function ($args) {
                return new Envelope($args[0]);
            });

        $this->gateway = new Gateway(
            $this->gatewayResolver->reveal(),
            $this->stripeManager->reveal(),
            $this->mercadopagoManager->reveal(),
            $this->messageBus->reveal(),
            $this->edenred->reveal()
        );

        $this->capturePayment = new CapturePayment(
            $this->gateway
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
            ->isEmpty()
            ->willReturn(false);
        $order
            ->getItemsTotal()
            ->willReturn(3000);
        $order
            ->getTotal()
            ->willReturn(3350);
        $order
            ->getLastPayment(PaymentInterface::STATE_AUTHORIZED)
            ->willReturn(null);
        $order
            ->getLastPayment(PaymentInterface::STATE_COMPLETED)
            ->willReturn($payment);
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
            ->getLastPayment(PaymentInterface::STATE_AUTHORIZED)
            ->willReturn($payment);
        $order
            ->getLastPayment(PaymentInterface::STATE_COMPLETED)
            ->willReturn(null);
        $order
            ->getNumber()
            ->willReturn('ABC123');

        $payment->setOrder($order->reveal());

        $this->stripeManager
            ->capture($payment)
            ->shouldBeCalled();

        call_user_func_array($this->capturePayment, [ new OrderFulfilled($order->reveal()) ]);

        $this
            ->messageBus
            ->dispatch(
                new RetrieveStripeFee($order->reveal()),
                Argument::type('array')
            )
            ->shouldHaveBeenCalled();
    }

    public function testDoesNothingForCancelledOrders()
    {
        $payment = new Payment();
        $payment->setAmount(3350);
        $payment->setCurrencyCode('EUR');

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
            ->getLastPayment(PaymentInterface::STATE_AUTHORIZED)
            ->willReturn($payment);
        $order
            ->getLastPayment(PaymentInterface::STATE_COMPLETED)
            ->willReturn(null);
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
            ->getLastPayment(PaymentInterface::STATE_AUTHORIZED)
            ->willReturn($payment);
        $order
            ->getLastPayment(PaymentInterface::STATE_COMPLETED)
            ->willReturn(null);
        $order
            ->getNumber()
            ->willReturn('ABC123');

        $payment->setOrder($order->reveal());

        $this->stripeManager
            ->capture($payment)
            ->shouldBeCalled();

        call_user_func_array($this->capturePayment, [ new OrderCancelled($order->reveal(), OrderInterface::CANCEL_REASON_NO_SHOW) ]);

        $this
            ->messageBus
            ->dispatch(
                new RetrieveStripeFee($order->reveal()),
                Argument::type('array')
            )
            ->shouldHaveBeenCalled();
    }
}
