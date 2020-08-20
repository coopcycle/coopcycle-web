<?php

namespace Tests\AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event\OrderCancelled;
use AppBundle\Domain\Order\Event\OrderFulfilled;
use AppBundle\Domain\Order\Reactor\CapturePayment;
use AppBundle\Entity\Sylius\Payment;
use AppBundle\Entity\Restaurant;
use AppBundle\Payment\Gateway;
use AppBundle\Payment\GatewayResolver;
use AppBundle\Service\MercadopagoManager;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Service\StripeManager;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Argument;
use Sylius\Component\Payment\Model\PaymentInterface;
use Stripe;

class CapturePaymentTest extends TestCase
{
    use ProphecyTrait;

    private $capturePayment;

    public function setUp(): void
    {
        $this->stripeManager = $this->prophesize(StripeManager::class);
        $this->mercadopagoManager = $this->prophesize(MercadopagoManager::class);

        $this->gatewayResolver = $this->prophesize(GatewayResolver::class);

        $this->gateway = new Gateway(
            $this->gatewayResolver->reveal(),
            $this->stripeManager->reveal(),
            $this->mercadopagoManager->reveal()
        );

        $this->capturePayment = new CapturePayment(
            $this->gateway
        );
    }

    public function testDoesNothingWhenChargeIsAlreadyCaptured()
    {
        $restaurant = new Restaurant();

        $source = Stripe\Source::constructFrom([
            'id' => 'src_12345678',
            'type' => 'giropay',
            'client_secret' => '',
            'redirect' => [
                'url' => 'http://example.com'
            ]
        ]);

        $payment = new Payment();
        $payment->setAmount(3350);
        $payment->setCurrencyCode('EUR');
        $payment->setSource($source);

        $order = $this->prophesize(OrderInterface::class);

        $order
            ->getRestaurant()
            ->willReturn($restaurant);
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

        $this->stripeManager
            ->capture(Argument::type(PaymentInterface::class))
            ->shouldNotBeCalled();

        call_user_func_array($this->capturePayment, [ new OrderFulfilled($order->reveal()) ]);
    }

    public function testCapturesPaymentForFulfilledOrders()
    {
        $restaurant = new Restaurant();

        $payment = new Payment();
        $payment->setAmount(3350);
        $payment->setCurrencyCode('EUR');

        $order = $this->prophesize(OrderInterface::class);

        $order
            ->getRestaurant()
            ->willReturn($restaurant);
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

        $this->stripeManager
            ->capture($payment)
            ->shouldBeCalled();

        call_user_func_array($this->capturePayment, [ new OrderFulfilled($order->reveal()) ]);
    }

    public function testDoesNothingForCancelledOrders()
    {
        $restaurant = new Restaurant();

        $payment = new Payment();
        $payment->setAmount(3350);
        $payment->setCurrencyCode('EUR');

        $order = $this->prophesize(OrderInterface::class);

        $order
            ->getRestaurant()
            ->willReturn($restaurant);
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

        $this->stripeManager
            ->capture($payment)
            ->shouldNotBeCalled();

        call_user_func_array($this->capturePayment, [ new OrderCancelled($order->reveal()) ]);
    }

    public function testCapturesPaymentForCancelledOrdersWithNoShowReason()
    {
        $restaurant = new Restaurant();

        $payment = new Payment();
        $payment->setAmount(3350);
        $payment->setCurrencyCode('EUR');

        $order = $this->prophesize(OrderInterface::class);

        $order
            ->getRestaurant()
            ->willReturn($restaurant);
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

        $this->stripeManager
            ->capture($payment)
            ->shouldBeCalled();

        call_user_func_array($this->capturePayment, [ new OrderCancelled($order->reveal(), OrderInterface::CANCEL_REASON_NO_SHOW) ]);
    }
}
