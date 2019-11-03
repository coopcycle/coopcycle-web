<?php

namespace Tests\AppBundle\Domain\Order\Handler;

use AppBundle\Domain\Order\Command\CreatePaymentIntent;
use AppBundle\Domain\Order\Event\CheckoutFailed;
use AppBundle\Domain\Order\Handler\CreatePaymentIntentHandler;
use AppBundle\Entity\StripePayment;
use AppBundle\Service\StripeManager;
use AppBundle\Sylius\Order\OrderInterface;
use PHPUnit\Framework\TestCase;
use SimpleBus\Message\Recorder\RecordsMessages;
use Stripe;
use Stripe\Exception\CardException;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Prophecy\Argument;

class CreatePaymentIntentHandlerTest extends TestCase
{
    private $eventRecorder;
    private $orderNumberAssigner;

    private $handler;

    public function setUp(): void
    {
        $this->eventRecorder = $this->prophesize(RecordsMessages::class);
        $this->orderNumberAssigner = $this->prophesize(OrderNumberAssignerInterface::class);
        $this->stripeManager = $this->prophesize(StripeManager::class);

        $this->handler = new CreatePaymentIntentHandler(
            $this->eventRecorder->reveal(),
            $this->orderNumberAssigner->reveal(),
            $this->stripeManager->reveal()
        );
    }

    public function testNumberIsAssigned()
    {
        $order = $this->prophesize(OrderInterface::class);

        $stripePayment = new StripePayment();

        $order
            ->getLastPayment(PaymentInterface::STATE_CART)
            ->willReturn($stripePayment);

        $this->orderNumberAssigner
            ->assignNumber($order)
            ->shouldBeCalled();

        $paymentIntent = Stripe\PaymentIntent::constructFrom([
            'id' => 'pi_12345678',
            'status' => 'requires_source_action',
            'next_action' => [
                'type' => 'use_stripe_sdk'
            ],
            'client_secret' => ''
        ]);

        $this->stripeManager
            ->createIntent($stripePayment)
            ->willReturn($paymentIntent);

        $command = new CreatePaymentIntent($order->reveal(), 'pm_123456');

        call_user_func_array($this->handler, [$command]);

        $this->assertEquals('pm_123456', $stripePayment->getPaymentMethod());
        $this->assertEquals('pi_12345678', $stripePayment->getPaymentIntent());
        $this->assertEquals('requires_action', $stripePayment->getPaymentIntentStatus());
        $this->assertEquals('use_stripe_sdk', $stripePayment->getPaymentIntentNextAction());
    }

    public function testException()
    {
        $order = $this->prophesize(OrderInterface::class);

        $stripePayment = new StripePayment();

        $order
            ->getLastPayment(PaymentInterface::STATE_CART)
            ->willReturn($stripePayment);

        $this->orderNumberAssigner
            ->assignNumber($order)
            ->shouldBeCalled();

        $paymentIntent = Stripe\PaymentIntent::constructFrom([
            'id' => 'pi_12345678',
            'status' => 'requires_source_action',
            'next_action' => [
                'type' => 'use_stripe_sdk'
            ],
            'client_secret' => ''
        ]);

        $this->stripeManager
            ->createIntent($stripePayment)
            ->willThrow(CardException::factory('Lorem ipsum'));

        $command = new CreatePaymentIntent($order->reveal(), 'pm_123456');

        call_user_func_array($this->handler, [$command]);

        $this->eventRecorder
            ->record(Argument::type(CheckoutFailed::class))
            ->shouldHaveBeenCalled();
    }
}
