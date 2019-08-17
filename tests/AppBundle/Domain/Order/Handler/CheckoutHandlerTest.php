<?php

namespace Tests\AppBundle\Domain\Order\Handler;

use AppBundle\Domain\Order\Command\Checkout;
use AppBundle\Domain\Order\Event\CheckoutFailed;
use AppBundle\Domain\Order\Event\CheckoutSucceeded;
use AppBundle\Domain\Order\Handler\CheckoutHandler;
use AppBundle\Entity\StripePayment;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Service\StripeManager;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\OrderTimeHelper;
use PHPUnit\Framework\TestCase;
use SimpleBus\Message\Recorder\RecordsMessages;
use Stripe;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Prophecy\Argument;

class CheckoutHandlerTest extends TestCase
{
    private $eventRecorder;
    private $orderNumberAssigner;
    private $stripeManager;

    private $handler;

    public function setUp(): void
    {
        $this->eventRecorder = $this->prophesize(RecordsMessages::class);
        $this->orderNumberAssigner = $this->prophesize(OrderNumberAssignerInterface::class);
        $this->stripeManager = $this->prophesize(StripeManager::class);

        $this->orderTimeHelper = $this->prophesize(OrderTimeHelper::class);

        $this->orderTimeHelper
            ->getAvailabilities(Argument::type(Order::class))
            ->willReturn([]);

        $this->handler = new CheckoutHandler(
            $this->eventRecorder->reveal(),
            $this->orderNumberAssigner->reveal(),
            $this->stripeManager->reveal(),
            $this->orderTimeHelper->reveal()
        );
    }

    public function testCheckoutLegacy()
    {
        $stripePayment = new StripePayment();
        $stripePayment->setState(PaymentInterface::STATE_CART);

        $charge = Stripe\Charge::constructFrom([
            'id' => 'ch_123456',
        ]);

        $order = new Order();
        $order->addPayment($stripePayment);

        $this->stripeManager
            ->authorize($stripePayment)
            ->willReturn($charge);

        $this->orderTimeHelper
            ->getAsap(Argument::type('array'))
            ->willReturn((new \DateTime())->format(\DateTime::ATOM));

        $this->eventRecorder
            ->record(Argument::type(CheckoutSucceeded::class))
            ->shouldBeCalled();

        $command = new Checkout($order, 'tok_123456');

        call_user_func_array($this->handler, [$command]);

        $this->assertNotNull($order->getShippedAt());
        $this->assertEquals('ch_123456', $stripePayment->getCharge());
    }

    public function testCheckoutWithPaymentIntent()
    {
        $stripePayment = new StripePayment();
        $stripePayment->setState(PaymentInterface::STATE_CART);

        $paymentIntent = Stripe\PaymentIntent::constructFrom([
            'id' => 'pi_12345678',
            'status' => 'requires_source_action',
            'next_action' => [
                'type' => 'use_stripe_sdk'
            ],
            'client_secret' => ''
        ]);
        $stripePayment->setPaymentIntent($paymentIntent);

        $order = new Order();
        $order->addPayment($stripePayment);

        $this->stripeManager
            ->confirmIntent($stripePayment)
            ->willReturn($paymentIntent);

        $this->orderTimeHelper
            ->getAsap(Argument::type('array'))
            ->willReturn((new \DateTime())->format(\DateTime::ATOM));

        $this->eventRecorder
            ->record(Argument::type(CheckoutSucceeded::class))
            ->shouldBeCalled();

        $command = new Checkout($order, 'pi_12345678');

        call_user_func_array($this->handler, [$command]);

        $this->assertNotNull($order->getShippedAt());
    }

    public function testCheckoutFailed()
    {
        $stripePayment = new StripePayment();
        $stripePayment->setState(PaymentInterface::STATE_CART);

        $paymentIntent = Stripe\PaymentIntent::constructFrom([
            'id' => 'pi_12345678',
            'status' => 'requires_source_action',
            'next_action' => [
                'type' => 'use_stripe_sdk'
            ],
            'client_secret' => ''
        ]);
        $stripePayment->setPaymentIntent($paymentIntent);

        $order = new Order();
        $order->addPayment($stripePayment);

        $this->stripeManager
            ->confirmIntent($stripePayment)
            ->willThrow(new \Exception('Lorem ipsum'));

        $this->eventRecorder
            ->record(Argument::type(CheckoutSucceeded::class))
            ->shouldNotBeCalled();

        $this->eventRecorder
            ->record(Argument::type(CheckoutFailed::class))
            ->shouldBeCalled();

        $command = new Checkout($order, 'tok_123456');

        call_user_func_array($this->handler, [$command]);

        $this->assertNull($order->getShippedAt());
    }
}
