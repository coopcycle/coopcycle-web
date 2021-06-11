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
use Stripe;
use Sylius\Component\Payment\Model\PaymentInterface;
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
        $this->mercadopagoManager = $this->prophesize(MercadopagoManager::class);
        $this->gatewayResolver = $this->prophesize(GatewayResolver::class);
        $this->edenred = $this->prophesize(EdenredClient::class);

        $this->gateway = new Gateway(
            $this->gatewayResolver->reveal(),
            $this->stripeManager->reveal(),
            $this->mercadopagoManager->reveal(),
            $this->edenred->reveal()
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
}
