<?php

namespace Tests\AppBundle\MessageHandler\Order\Command;

use AppBundle\DataType\TsRange;
use AppBundle\Message\Order\Command\Checkout;
use AppBundle\Domain\Order\Event\CheckoutFailed;
use AppBundle\Domain\Order\Event\CheckoutSucceeded;
use AppBundle\MessageHandler\Order\Command\CheckoutHandler;
use AppBundle\Edenred\Client as EdenredClient;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\Payment;
use AppBundle\Payment\Gateway;
use AppBundle\Payment\GatewayResolver;
use AppBundle\Service\NullLoggingUtils;
use AppBundle\Service\StripeManager;
use AppBundle\Utils\OrderTimeHelper;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use Stripe;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentMethod;
use Prophecy\Argument;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Translation\IdentityTranslator;

class CheckoutHandlerTest extends TestCase
{
    use ProphecyTrait;

    private $eventBus;
    private $orderNumberAssigner;
    private $stripeManager;

    private $handler;

    public function setUp(): void
    {
        $this->eventBus = $this->prophesize(MessageBusInterface::class);

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

        $this->orderTimeHelper = $this->prophesize(OrderTimeHelper::class);

        $this->handler = new CheckoutHandler(
            $this->eventBus->reveal(),
            $this->orderNumberAssigner->reveal(),
            $this->gateway,
            $this->orderTimeHelper->reveal(),
            new IdentityTranslator(),
            new NullLogger(),
            new NullLoggingUtils()
        );
    }

    private function createCardPayment(): Payment
    {
        $paymentMethod = new PaymentMethod();
        $paymentMethod->setCode('CARD');

        $payment = new Payment();
        $payment->setState(PaymentInterface::STATE_CART);
        $payment->setMethod($paymentMethod);
        $payment->setPaymentIntent(Stripe\PaymentIntent::constructFrom([
            'id' => 'pi_12345678',
            'status' => 'requires_source_action',
            'next_action' => [
                'type' => 'use_stripe_sdk'
            ],
            'client_secret' => ''
        ]));

        return $payment;
    }

    public function testCheckoutAssignsShippingTimeRangeBeforePayment()
    {
        $payment = $this->createCardPayment();

        $range = TsRange::create(new \DateTime('2026-09-09 21:20:00'), new \DateTime('2026-09-09 21:30:00'));

        $order = $this->prophesize(Order::class);
        $order->isFree()->willReturn(false);
        $order->hasVendor()->willReturn(true);
        $order->getShippingTimeRange()->willReturn(null);
        $order->getPayments()->willReturn(new ArrayCollection([$payment]));
        $order->setShippingTimeRange($range)->shouldBeCalledOnce();
        $order->setShippingTimeRange(null)->shouldNotBeCalled();

        $this->orderTimeHelper
            ->getShippingTimeRange($order->reveal())
            ->willReturn($range);

        $this->stripeManager
            ->confirmIntent($payment)
            ->willReturn(Stripe\PaymentIntent::constructFrom([
                'id' => 'pi_12345678',
                'status' => 'requires_capture',
            ]));

        $this->eventBus
            ->dispatch(Argument::that(fn (Envelope $envelope) => $envelope->getMessage() instanceof CheckoutSucceeded))
            ->willReturn(new Envelope(new CheckoutSucceeded($order->reveal())))
            ->shouldBeCalledOnce();

        call_user_func_array($this->handler, [new Checkout($order->reveal(), 'pi_12345678')]);
    }

    public function testCheckoutFailsWhenNoShippingTimeRangeIsAvailable()
    {
        $payment = $this->createCardPayment();

        $order = $this->prophesize(Order::class);
        $order->isFree()->willReturn(false);
        $order->hasVendor()->willReturn(true);
        $order->getShippingTimeRange()->willReturn(null);
        $order->getLastPayment(PaymentInterface::STATE_CART)->willReturn($payment);
        $order->setShippingTimeRange(Argument::any())->shouldNotBeCalled();

        $this->orderTimeHelper
            ->getShippingTimeRange($order->reveal())
            ->willReturn(null);

        // The customer must not be charged
        $this->stripeManager
            ->confirmIntent(Argument::any())
            ->shouldNotBeCalled();

        $this->eventBus
            ->dispatch(Argument::that(fn (Envelope $envelope) => $envelope->getMessage() instanceof CheckoutSucceeded))
            ->shouldNotBeCalled();

        $this->eventBus
            ->dispatch(Argument::that(function (Envelope $envelope) use ($payment) {
                $event = $envelope->getMessage();

                return $event instanceof CheckoutFailed
                    && $event->getPayment() === $payment
                    && $event->getReason() === 'order.shippedAt.notAvailable';
            }))
            ->willReturn(new Envelope(new CheckoutFailed($order->reveal(), $payment)))
            ->shouldBeCalledOnce();

        call_user_func_array($this->handler, [new Checkout($order->reveal(), 'pi_12345678')]);
    }

    public function testCheckoutRestoresAsapWhenPaymentFails()
    {
        $payment = $this->createCardPayment();

        $range = TsRange::create(new \DateTime('2026-09-09 21:20:00'), new \DateTime('2026-09-09 21:30:00'));

        $order = $this->prophesize(Order::class);
        $order->isFree()->willReturn(false);
        $order->hasVendor()->willReturn(true);
        $order->getShippingTimeRange()->willReturn(null);
        $order->getPayments()->willReturn(new ArrayCollection([$payment]));
        $order->setShippingTimeRange($range)->shouldBeCalledOnce();
        $order->setShippingTimeRange(null)->shouldBeCalledOnce();

        $this->orderTimeHelper
            ->getShippingTimeRange($order->reveal())
            ->willReturn($range);

        $this->stripeManager
            ->confirmIntent($payment)
            ->willThrow(new \Exception('Your card was declined.'));

        $this->eventBus
            ->dispatch(Argument::that(fn (Envelope $envelope) => $envelope->getMessage() instanceof CheckoutFailed))
            ->willReturn(new Envelope(new CheckoutFailed($order->reveal(), $payment)))
            ->shouldBeCalledOnce();

        call_user_func_array($this->handler, [new Checkout($order->reveal(), 'pi_12345678')]);
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

        $this->eventBus
            ->dispatch(Argument::that(function(Envelope $envelope){
                $this->assertInstanceOf(CheckoutSucceeded::class, $envelope->getMessage());
                return true;
            }))
            ->willReturn(new Envelope(new CheckoutSucceeded($order)))
            ->shouldBeCalledOnce();

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

        $this->eventBus
            ->dispatch(Argument::that(function(Envelope $envelope){
                if ($envelope->getMessage() instanceof CheckoutSucceeded) {
                    return true;
                }
                return false;
            }))
            ->willReturn(new Envelope(new CheckoutSucceeded($order)))
            ->shouldNotBeCalled();

        $this->eventBus
            ->dispatch(Argument::that(function(Envelope $envelope){
                if ($envelope->getMessage() instanceof CheckoutFailed) {
                    return true;
                }
            }))
            ->willReturn(new Envelope(new CheckoutFailed($order, $payment, 'Lorem ipsum')))
            ->shouldBeCalledOnce();

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
        $order
            ->hasVendor()
            ->willReturn(false);

        $this->stripeManager
            ->confirmIntent(Argument::type(Payment::class))
            ->shouldNotBeCalled();

        $this->eventBus
            ->dispatch(Argument::that(function(Envelope $envelope){
                if ($envelope->getMessage() instanceof CheckoutSucceeded) {
                    return true;
                }
                return false;
            }))
            ->willReturn(new Envelope(new CheckoutSucceeded($order->reveal())))
            ->shouldBeCalledOnce();

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
            ->hasVendor()
            ->willReturn(false);
        $order
            ->getPayments()
            ->willReturn(new ArrayCollection([$edenredPayment, $cardPayment]));

        $this->eventBus
            ->dispatch(Argument::that(function(Envelope $envelope){
                if ($envelope->getMessage() instanceof CheckoutSucceeded) {
                    return true;
                }
                return false;
            }))
            ->willReturn(new Envelope(new CheckoutSucceeded($order->reveal())))
            ->shouldBeCalledOnce();

        $this->eventBus
            ->dispatch(Argument::that(function(Envelope $envelope){
                if ($envelope->getMessage() instanceof CheckoutFailed) {
                    return true;
                }
            }))
            ->willReturn(new Envelope(new CheckoutFailed($order->reveal(), $cardPayment, 'Lorem ipsum')))
            ->shouldNotBeCalled();

        $this->gateway = $this->prophesize(Gateway::class);

        $this->handler = new CheckoutHandler(
            $this->eventBus->reveal(),
            $this->orderNumberAssigner->reveal(),
            $this->gateway->reveal(),
            $this->orderTimeHelper->reveal(),
            new IdentityTranslator(),
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
