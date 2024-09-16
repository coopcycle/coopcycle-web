<?php

namespace Tests\AppBundle\Domain\Order\Handler;

use AppBundle\Domain\Order\Command\Checkout;
use AppBundle\Domain\Order\Event\CheckoutFailed;
use AppBundle\Domain\Order\Event\CheckoutSucceeded;
use AppBundle\Domain\Order\Handler\CheckoutHandler;
use AppBundle\Edenred\Client as EdenredClient;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\Payment;
use AppBundle\Payment\Gateway;
use AppBundle\Payment\GatewayResolver;
use AppBundle\Service\NullLoggingUtils;
use AppBundle\Service\StripeManager;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use SimpleBus\Message\Recorder\RecordsMessages;
use Stripe;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentMethod;
use Prophecy\Argument;

class CheckoutHandlerTest extends TestCase
{
    use ProphecyTrait;

    private $eventRecorder;
    private $orderNumberAssigner;
    private $stripeManager;

    private $handler;

    public function setUp(): void
    {
        $this->eventRecorder = $this->prophesize(RecordsMessages::class);
        $this->orderNumberAssigner = $this->prophesize(OrderNumberAssignerInterface::class);
        $this->stripeManager = $this->prophesize(StripeManager::class);
        $this->gatewayResolver = $this->prophesize(GatewayResolver::class);
        $this->edenredClient = $this->prophesize(EdenredClient::class);

        $this->stripeGateway = new Gateway\Stripe($this->stripeManager->reveal());
        $this->edenredGateway = new Gateway\Edenred($this->edenredClient->reveal());

        $this->gateway = new Gateway(
            $this->gatewayResolver->reveal(),
            [
                'stripe' => $this->stripeGateway,
                'edenred' => $this->edenredGateway,
            ]
        );

        $this->handler = new CheckoutHandler(
            $this->eventRecorder->reveal(),
            $this->orderNumberAssigner->reveal(),
            $this->gateway,
            new NullLogger(),
            new NullLoggingUtils()
        );
    }

    public function testCheckoutWithPaymentIntent()
    {
        $paymentMethod = new PaymentMethod();
        $paymentMethod->setCode('CARD');

        $payment = new Payment();
        $payment->setState(PaymentInterface::STATE_CART);
        $payment->setMethod($paymentMethod);

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
    }

    public function testCheckoutFailed()
    {
        $paymentMethod = new PaymentMethod();
        $paymentMethod->setCode('CARD');

        $payment = new Payment();
        $payment->setState(PaymentInterface::STATE_CART);
        $payment->setMethod($paymentMethod);

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
            ->isFree()
            ->willReturn(true);

        $this->stripeManager
            ->confirmIntent(Argument::type(Payment::class))
            ->shouldNotBeCalled();

        $this->eventRecorder
            ->record(Argument::type(CheckoutSucceeded::class))
            ->shouldBeCalled();

        $command = new Checkout($order->reveal());

        call_user_func_array($this->handler, [$command]);
    }

    public function testCheckoutWithEdenredAndComplement()
    {
        $card = new PaymentMethod();
        $card->setCode('CARD');

        $paymentIntent = Stripe\PaymentIntent::constructFrom([
            'id' => 'pi_12345678',
            'status' => 'requires_source_action',
            'next_action' => [
                'type' => 'use_stripe_sdk'
            ],
            'client_secret' => ''
        ]);

        $cardPayment = new Payment();
        $cardPayment->setState(PaymentInterface::STATE_CART);
        $cardPayment->setMethod($card);
        $cardPayment->setPaymentIntent($paymentIntent);

        $edenred = new PaymentMethod();
        $edenred->setCode('EDENRED');

        $edenredPayment = new Payment();
        $edenredPayment->setState(PaymentInterface::STATE_CART);
        $edenredPayment->setMethod($edenred);

        $order = $this->prophesize(Order::class);

        $order
            ->isFree()
            ->willReturn(false);
        $order
            ->getPayments()
            ->willReturn(new ArrayCollection([$edenredPayment, $cardPayment]));

        $this->eventRecorder
            ->record(Argument::type(CheckoutSucceeded::class))
            ->shouldBeCalled();

        $this->gateway = $this->prophesize(Gateway::class);

        $this->handler = new CheckoutHandler(
            $this->eventRecorder->reveal(),
            $this->orderNumberAssigner->reveal(),
            $this->gateway->reveal(),
            new NullLogger(),
            new NullLoggingUtils()
        );

        $command = new Checkout($order->reveal(), 'pi_12345678');

        call_user_func_array($this->handler, [$command]);

        $this->gateway
            ->authorize(Argument::type(Payment::class), ['token' => 'pi_12345678'])
            ->shouldHaveBeenCalledTimes(2);

        $this->gateway
            ->authorize(Argument::type(Payment::class), ['token' => 'pi_12345678'])
            ->shouldHave(function ($calls) use ($edenredPayment, $cardPayment) {
                Assert::assertSame($cardPayment, $calls[0]->getArguments()[0]);
                Assert::assertSame($edenredPayment, $calls[1]->getArguments()[0]);
            });
    }
}
