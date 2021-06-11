<?php

namespace Tests\AppBundle\Domain\Order\Handler;

use AppBundle\Domain\Order\Command\Refund as RefundCommand;
use AppBundle\Domain\Order\Event\OrderCreated;
use AppBundle\Domain\Order\Handler\RefundHandler;
use AppBundle\Entity\Refund;
use AppBundle\Entity\Sylius\Payment;
use AppBundle\Payment\Gateway;
use AppBundle\Sylius\Order\OrderInterface;
use Prophecy\PhpUnit\ProphecyTrait;
use SimpleBus\Message\Recorder\RecordsMessages;
use SM\Factory\FactoryInterface;
use Stripe;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Prophecy\Argument;

class RefundHandlerTest extends KernelTestCase
{
    use ProphecyTrait;

    private $stripeManager;
    private $stateMachineFactory;
    private $eventRecorder;

    private $handler;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->gateway = $this->prophesize(Gateway::class);
        // @see https://symfony.com/blog/new-in-symfony-4-1-simpler-service-testing
        $this->stateMachineFactory = self::$container->get(FactoryInterface::class);
        $this->eventRecorder = $this->prophesize(RecordsMessages::class);

        $this->handler = new RefundHandler(
            $this->gateway->reveal(),
            $this->stateMachineFactory,
            $this->eventRecorder->reveal()
        );
    }

    public function testRefund()
    {
        $order = $this->prophesize(OrderInterface::class);

        $order
            ->getTotal()
            ->willReturn(2000);

        $payment = new Payment();
        $payment->setState(PaymentInterface::STATE_COMPLETED);
        $payment->setOrder($order->reveal());

        $refund = new Refund();

        $this->gateway
            ->refund($payment, 500)
            ->willReturn($refund)
            ->shouldBeCalled();

        $command = new RefundCommand($payment, 500, Refund::LIABLE_PARTY_PLATFORM, 'Lorem ipsum');

        $this->assertFalse($payment->hasRefunds());

        call_user_func_array($this->handler, [ $command ]);

        $this->assertEquals(Refund::LIABLE_PARTY_PLATFORM, $refund->getLiableParty());
        $this->assertEquals('Lorem ipsum', $refund->getComments());
    }
}
