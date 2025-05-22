<?php

namespace Tests\AppBundle\MessageHandler\Order\Command;

use AppBundle\Message\Order\Command\Refund as RefundCommand;
use AppBundle\Domain\Order\Event\OrderCreated;
use AppBundle\MessageHandler\Order\Command\RefundHandler;
use AppBundle\Entity\Refund;
use AppBundle\Entity\Sylius\Payment;
use AppBundle\Payment\Gateway;
use AppBundle\Sylius\Order\OrderInterface;
use Prophecy\PhpUnit\ProphecyTrait;
use SM\Factory\FactoryInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

class RefundHandlerTest extends KernelTestCase
{
    use ProphecyTrait;

    private $stripeManager;
    private $stateMachineFactory;
    private $eventBus;

    private $handler;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->gateway = $this->prophesize(Gateway::class);
        // @see https://symfony.com/blog/new-in-symfony-4-1-simpler-service-testing
        $this->stateMachineFactory = self::$container->get(FactoryInterface::class);
        $this->eventBus = $this->prophesize(MessageBusInterface::class);

        $this->handler = new RefundHandler(
            $this->gateway->reveal(),
            $this->stateMachineFactory,
            $this->eventBus->reveal()
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
