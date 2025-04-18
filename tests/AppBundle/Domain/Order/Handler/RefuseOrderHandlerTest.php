<?php

namespace Tests\AppBundle\Domain\Order\Handler;

use AppBundle\Domain\Order\Command\RefuseOrder;
use AppBundle\Domain\Order\Event\OrderRefused;
use AppBundle\Domain\Order\Handler\RefuseOrderHandler;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\Payment;
use AppBundle\Sylius\Order\OrderInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use SimpleBus\Message\Recorder\RecordsMessages;
use Stripe;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Tests\AppBundle\StripeTrait;

class RefuseOrderHandlerTest extends TestCase
{
    use ProphecyTrait;
    use StripeTrait {
        setUp as setUpStripe;
    }

    private $eventRecorder;
    private $stripeManager;

    private $handler;

    public function setUp(): void
    {
        $this->setUpStripe();

        $this->eventRecorder = $this->prophesize(RecordsMessages::class);
        $this->orderNumberAssigner = $this->prophesize(OrderNumberAssignerInterface::class);

        $this->handler = new RefuseOrderHandler(
            $this->eventRecorder->reveal()
        );
    }

    public function testRefuseOrderWithCreditCardPayment()
    {
        $charge = Stripe\Charge::constructFrom([
            'id' => 'ch_12345678',
        ]);

        $payment = new Payment();
        $payment->setAmount(3000);
        $payment->setState(PaymentInterface::STATE_AUTHORIZED);
        $payment->setCurrencyCode('EUR');
        $payment->setCharge($charge);

        $order = new Order();
        $order->addPayment($payment);

        $this->eventRecorder
            ->record(Argument::type(OrderRefused::class))
            ->shouldBeCalled();

        $command = new RefuseOrder($order, 'Out of stock');

        call_user_func_array($this->handler, [ $command ]);
    }
}
