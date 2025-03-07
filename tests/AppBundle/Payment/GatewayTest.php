<?php

namespace Tests\AppBundle\Payment;

use AppBundle\Edenred\Client as EdenredClient;
use AppBundle\Entity\Refund;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\Payment;
use AppBundle\Payment\Gateway;
use AppBundle\Payment\GatewayResolver;
use AppBundle\Service\MercadopagoManager;
use AppBundle\Service\StripeManager;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use SimpleBus\Message\Recorder\RecordsMessages;
use Stripe;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentMethod;
use Prophecy\Argument;

class GatewayTest extends TestCase
{
    use ProphecyTrait;

    private $eventRecorder;
    private $stripeManager;

    private $handler;

    public function setUp(): void
    {
        $this->eventRecorder = $this->prophesize(RecordsMessages::class);
        $this->stripeManager = $this->prophesize(StripeManager::class);
        $this->gatewayResolver = $this->prophesize(GatewayResolver::class);
        $this->edenred = $this->prophesize(EdenredClient::class);

        $this->stripeGateway = new Gateway\Stripe($this->stripeManager->reveal());
        $this->edenredGateway = new Gateway\Edenred($this->edenred->reveal());

        $this->gateway = new Gateway(
            $this->gatewayResolver->reveal(),
            [
                'stripe' => $this->stripeGateway,
                'edenred' => $this->edenredGateway,
            ]
        );
    }

    public function testAuthorizeWithPaymentIntent()
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
            ->willReturn($paymentIntent)
            ->shouldBeCalled();

        $this->gateway->authorize($payment, ['token' => 'pi_12345678']);
    }

    public function testAuthorizeWithPaymentIntentMismatch()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Payment Intent mismatch');

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
            ->shouldNotBeCalled();

        $this->gateway->authorize($payment, ['token' => 'pi_98765432']);
    }

    public function testRefund()
    {
        $payment = new Payment();
        $payment->setState(PaymentInterface::STATE_COMPLETED);

        $stripeRefund = Stripe\Refund::constructFrom([
            'id' => 're_123456',
            'amount' => 500,
        ]);

        $this->stripeManager
            ->refund($payment, 500)
            ->willReturn($stripeRefund)
            ->shouldBeCalled();

        $this->assertFalse($payment->hasRefunds());

        $this->gateway->refund($payment, 500);

        $this->assertTrue($payment->hasRefunds());
        $this->assertCount(1, $payment->getRefunds());
        $this->assertInstanceOf(Collection::class, $payment->getRefunds());
    }

    public function testRefundEdenred()
    {
        $method = new PaymentMethod();
        $method->setCode('EDENRED');

        $payment = new Payment();
        $payment->setState(PaymentInterface::STATE_COMPLETED);
        $payment->setMethod($method);
        $payment->setAmount(3800);

        $this->edenred
            ->refund($payment, 1000)
            ->shouldBeCalled();

        $this->assertFalse($payment->hasRefunds());

        $this->gateway->refund($payment, 1000);

        $this->assertTrue($payment->hasRefunds());
        $this->assertCount(1, $payment->getRefunds());
        $this->assertInstanceOf(Collection::class, $payment->getRefunds());
    }

    public function testAuthorizeAndAttachPaymentMethod()
    {
        $payment = new Payment();
        $payment->setState(PaymentInterface::STATE_CART);

        $paymentIntent = Stripe\PaymentIntent::constructFrom([
            'id' => 'pi_12345678',
            'status' => 'succeeded',
            'next_action' => null,
            'client_secret' => ''
        ]);
        $payment->setPaymentIntent($paymentIntent);
        $payment->setPaymentDataToSaveAndReuse('pm_12345678');

        $order = new Order();
        $order->addPayment($payment);

        $this->stripeManager
            ->attachPaymentMethodToCustomer($payment)
            ->shouldBeCalled();

        $this->gateway->authorize($payment, ['token' => 'pi_12345678']);
    }
}
