<?php

namespace Tests\AppBundle\MessageHandler\Order\Command;

use AppBundle\Message\Order\Command\RefuseOrder;
use AppBundle\Domain\Order\Event\OrderRefused;
use AppBundle\MessageHandler\Order\Command\RefuseOrderHandler;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\Payment;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Stripe;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Tests\AppBundle\StripeTrait;

class RefuseOrderHandlerTest extends TestCase
{
    use ProphecyTrait;
    use StripeTrait {
        setUp as setUpStripe;
    }

    private $eventBus;
    private $stripeManager;

    private $handler;

    public function setUp(): void
    {
        $this->setUpStripe();

        $this->eventBus = $this->prophesize(MessageBusInterface::class);
        $this->orderNumberAssigner = $this->prophesize(OrderNumberAssignerInterface::class);

        $this->handler = new RefuseOrderHandler(
            $this->eventBus->reveal()
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

        $this->eventBus
            ->dispatch(Argument::that(function(Envelope $envelope){
                if ($envelope->getMessage() instanceof OrderRefused) {
                    return true;
                }
            }))
            ->willReturn(new Envelope(new OrderRefused($order)))
            ->shouldBeCalledOnce();

        $command = new RefuseOrder($order, 'Out of stock');

        call_user_func_array($this->handler, [ $command ]);
    }
}
