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
use PHPUnit\Framework\TestCase;
use SimpleBus\Message\Recorder\RecordsMessages;
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

        $this->handler = new CheckoutHandler(
            $this->eventRecorder->reveal(),
            $this->orderNumberAssigner->reveal(),
            $this->stripeManager->reveal()
        );
    }

    public function testCheckoutSucceeded()
    {
        $stripePayment = new StripePayment();
        $stripePayment->setState(PaymentInterface::STATE_CART);

        $order = new Order();
        $order->addPayment($stripePayment);

        $charge = new \stdClass();
        $charge->id = 'ch_123456';

        $this->stripeManager
            ->authorize($stripePayment)
            ->willReturn($charge);

        $this->orderNumberAssigner
            ->assignNumber($order)
            ->shouldBeCalled();

        $this->eventRecorder
            ->record(Argument::type(CheckoutSucceeded::class))
            ->shouldBeCalled();

        $command = new Checkout($order, 'tok_123456');

        call_user_func_array($this->handler, [$command]);

        $this->assertEquals('tok_123456', $stripePayment->getStripeToken());
        $this->assertEquals('ch_123456', $stripePayment->getCharge());
    }

    public function testCheckoutFailed()
    {
        $stripePayment = new StripePayment();
        $stripePayment->setState(PaymentInterface::STATE_CART);

        $order = new Order();
        $order->addPayment($stripePayment);

        $this->stripeManager
            ->authorize($stripePayment)
            ->willThrow(new \Exception('Lorem ipsum'));

        $this->orderNumberAssigner
            ->assignNumber($order)
            ->shouldBeCalled();

        $this->eventRecorder
            ->record(Argument::type(CheckoutSucceeded::class))
            ->shouldNotBeCalled();

        $this->eventRecorder
            ->record(Argument::type(CheckoutFailed::class))
            ->shouldBeCalled();

        $command = new Checkout($order, 'tok_123456');

        call_user_func_array($this->handler, [$command]);
    }
}
