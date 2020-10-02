<?php

namespace Tests\AppBundle\Domain\Order\Handler;

use AppBundle\DataType\TsRange;
use AppBundle\Domain\Order\Command\Checkout;
use AppBundle\Domain\Order\Event\CheckoutFailed;
use AppBundle\Domain\Order\Event\CheckoutSucceeded;
use AppBundle\Domain\Order\Handler\CheckoutHandler;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\Payment;
use AppBundle\Payment\Gateway;
use AppBundle\Payment\GatewayResolver;
use AppBundle\Service\MercadopagoManager;
use AppBundle\Service\StripeManager;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\OrderTimeHelper;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use SimpleBus\Message\Recorder\RecordsMessages;
use Stripe;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Prophecy\Argument;

class CheckoutHandlerTest extends TestCase
{
    use ProphecyTrait;

    private $eventRecorder;
    private $orderNumberAssigner;
    private $stripeManager;

    private $handler;
    private $asap;

    public function setUp(): void
    {
        $this->eventRecorder = $this->prophesize(RecordsMessages::class);
        $this->orderNumberAssigner = $this->prophesize(OrderNumberAssignerInterface::class);
        $this->stripeManager = $this->prophesize(StripeManager::class);
        $this->mercadopagoManager = $this->prophesize(MercadopagoManager::class);
        $this->gatewayResolver = $this->prophesize(GatewayResolver::class);

        $this->gateway = new Gateway(
            $this->gatewayResolver->reveal(),
            $this->stripeManager->reveal(),
            $this->mercadopagoManager->reveal()
        );

        $this->orderTimeHelper = $this->prophesize(OrderTimeHelper::class);

        $this->shippingTimeRange = new TsRange();
        $this->shippingTimeRange->setLower(new \DateTime('2020-04-09 19:55:00'));
        $this->shippingTimeRange->setUpper(new \DateTime('2020-04-09 20:05:00'));

        $this->asap = new \DateTime('2020-04-09 20:00:00');

        $this->orderTimeHelper
            ->getShippingTimeRange(Argument::type(OrderInterface::class))
            ->willReturn($this->shippingTimeRange);

        $this->handler = new CheckoutHandler(
            $this->eventRecorder->reveal(),
            $this->orderNumberAssigner->reveal(),
            $this->stripeManager->reveal(),
            $this->gateway,
            $this->orderTimeHelper->reveal()
        );
    }

    public function testCheckoutLegacy()
    {
        $payment = new Payment();
        $payment->setState(PaymentInterface::STATE_CART);

        $charge = Stripe\Charge::constructFrom([
            'id' => 'ch_123456',
            'captured' => true,
        ]);

        $order = new Order();
        $order->addPayment($payment);

        $this->stripeManager
            ->authorize($payment)
            ->willReturn($charge);

        $this->eventRecorder
            ->record(Argument::type(CheckoutSucceeded::class))
            ->shouldBeCalled();

        $command = new Checkout($order, 'tok_123456');

        call_user_func_array($this->handler, [$command]);

        $this->assertNotNull($order->getShippingTimeRange());
        $this->assertEquals($this->shippingTimeRange, $order->getShippingTimeRange());
        $this->assertNotNull($order->getShippedAt());
        $this->assertEquals($this->asap, $order->getShippedAt());
        $this->assertEquals('ch_123456', $payment->getCharge());
    }

    public function testCheckoutWithPaymentIntent()
    {
        $payment = new Payment();
        $payment->setState(PaymentInterface::STATE_CART);

        $paymentIntent = Stripe\PaymentIntent::constructFrom([
            'id' => 'pi_12345678',
            'status' => 'requires_source_action',
            'next_action' => [
                'type' => 'use_stripe_sdk'
            ],
            'client_secret' => ''
        ]);
        $payment->setPaymentIntent($paymentIntent);

        $order = new Order();
        $order->addPayment($payment);

        $this->stripeManager
            ->confirmIntent($payment)
            ->willReturn($paymentIntent);

        $this->eventRecorder
            ->record(Argument::type(CheckoutSucceeded::class))
            ->shouldBeCalled();

        $command = new Checkout($order, 'pi_12345678');

        call_user_func_array($this->handler, [$command]);

        $this->assertNotNull($order->getShippingTimeRange());
        $this->assertEquals($this->shippingTimeRange, $order->getShippingTimeRange());
        $this->assertNotNull($order->getShippedAt());
        $this->assertEquals($this->asap, $order->getShippedAt());
    }

    public function testCheckoutFailed()
    {
        $payment = new Payment();
        $payment->setState(PaymentInterface::STATE_CART);

        $paymentIntent = Stripe\PaymentIntent::constructFrom([
            'id' => 'pi_12345678',
            'status' => 'requires_source_action',
            'next_action' => [
                'type' => 'use_stripe_sdk'
            ],
            'client_secret' => ''
        ]);
        $payment->setPaymentIntent($paymentIntent);

        $order = new Order();
        $order->addPayment($payment);

        $this->stripeManager
            ->confirmIntent($payment)
            ->willThrow(new \Exception('Lorem ipsum'));

        $this->eventRecorder
            ->record(Argument::type(CheckoutSucceeded::class))
            ->shouldNotBeCalled();

        $this->eventRecorder
            ->record(Argument::type(CheckoutFailed::class))
            ->shouldBeCalled();

        $command = new Checkout($order, 'tok_123456');

        call_user_func_array($this->handler, [$command]);

        $this->assertNull($order->getShippingTimeRange());
        $this->assertNull($order->getShippedAt());
    }

    public function testCheckoutWithFreeOrder()
    {
        $order = $this->prophesize(Order::class);

        $order
            ->getLastPayment(PaymentInterface::STATE_CART)
            ->willReturn(null);
        $order
            ->getLastPayment(PaymentInterface::STATE_PROCESSING)
            ->willReturn(null);
        $order
            ->isEmpty()
            ->willReturn(false);
        $order
            ->getItemsTotal()
            ->willReturn(1000);
        $order
            ->getTotal()
            ->willReturn(0);
        $order
            ->getShippingTimeRange()
            ->willReturn(null);

        $this->stripeManager
            ->confirmIntent(Argument::type(Payment::class))
            ->shouldNotBeCalled();
        $this->stripeManager
            ->authorize(Argument::type(Payment::class))
            ->shouldNotBeCalled();

        $order
            ->setShippingTimeRange(Argument::type(TsRange::class))
            ->shouldBeCalled();
        $this->eventRecorder
            ->record(Argument::type(CheckoutSucceeded::class))
            ->shouldBeCalled();

        $command = new Checkout($order->reveal());

        call_user_func_array($this->handler, [$command]);
    }
}
