<?php

namespace Tests\AppBundle\MessageHandler\Order\Command;

use AppBundle\Message\Order\Command\Refund as RefundCommand;
use AppBundle\MessageHandler\Order\Command\RefundHandler;
use AppBundle\Entity\Refund;
use AppBundle\Entity\Sylius\Payment;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Payment\Gateway;
use AppBundle\Payment\GatewayResolver;
use AppBundle\Service\PaygreenManager;
use Doctrine\Common\Collections\ArrayCollection;
use Paygreen\Sdk\Payment\V3\Client as PaygreenClient;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Http\Message\ResponseInterface;
use SM\Factory\FactoryInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

class RefundHandlerTest extends KernelTestCase
{
    use ProphecyTrait;

    private $stateMachineFactory;
    private $eventBus;

    private $handler;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->gateway = $this->prophesize(Gateway::class);
        $this->gatewayResolver = $this->prophesize(GatewayResolver::class);

        $this->paygreenClient = $this->prophesize(PaygreenClient::class);
        $this->paygreenManager = $this->prophesize(PaygreenManager::class);

        $this->stateMachineFactory = self::getContainer()->get(FactoryInterface::class);
        $this->eventBus = $this->prophesize(MessageBusInterface::class);

        $this->handler = new RefundHandler(
            $this->gateway->reveal(),
            $this->gatewayResolver->reveal(),
            $this->paygreenClient->reveal(),
            $this->paygreenManager->reveal(),
            $this->stateMachineFactory,
            $this->eventBus->reveal()
        );
    }

    public function testRefund()
    {
        $order = $this->prophesize(Order::class);

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

    public function testFullRefund()
    {
        $order = $this->prophesize(Order::class);

        $order
            ->getTotal()
            ->willReturn(2000);

        $payment = new Payment();
        $payment->setState(PaymentInterface::STATE_COMPLETED);
        $payment->setOrder($order->reveal());
        $payment->setAmount(2000);

        $order->getPayments()
            ->willReturn(new ArrayCollection([ $payment ]));
        $order
            ->getLastPayment(PaymentInterface::STATE_COMPLETED)
            ->willReturn($payment);

        $refund = new Refund();

        $this->gateway
            ->refund($payment, 2000)
            ->willReturn($refund)
            ->shouldBeCalled();

        $command = new RefundCommand($order->reveal(), null, Refund::LIABLE_PARTY_PLATFORM, 'Lorem ipsum');

        $this->assertFalse($payment->hasRefunds());

        call_user_func_array($this->handler, [ $command ]);

        $this->assertEquals(Refund::LIABLE_PARTY_PLATFORM, $refund->getLiableParty());
        $this->assertEquals('Lorem ipsum', $refund->getComments());
    }

    public function testFullRefundWith2PayGreenPayments()
    {
        $order = $this->prophesize(Order::class);

        $creditCard = new Payment();
        $creditCard->setState(PaymentInterface::STATE_COMPLETED);
        $creditCard->setOrder($order->reveal());
        $creditCard->setAmount(2000);
        $creditCard->setDetails(['paygreen_payment_order_id' => 'po_123456']);

        $this->gatewayResolver
            ->resolveForPayment($creditCard)
            ->willReturn('paygreen');

        $conecs = new Payment();
        $conecs->setState(PaymentInterface::STATE_COMPLETED);
        $conecs->setOrder($order->reveal());
        $conecs->setAmount(500);

        $this->gatewayResolver
            ->resolveForPayment($conecs)
            ->willReturn('paygreen');

        $order
            ->getTotal()
            ->willReturn($creditCard->getAmount() + $conecs->getAmount());
        $order
            ->getPayments()
            ->willReturn(new ArrayCollection([ $creditCard, $conecs ]));
        $order
            ->getLastPaymentByMethod('CARD', PaymentInterface::STATE_COMPLETED)
            ->willReturn($creditCard);

        $refund = new Refund();

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(200);

        $this->paygreenManager
            ->authenticate()
            ->shouldBeCalled();

        $this->paygreenClient
            ->refundPaymentOrder('po_123456')
            ->willReturn($response)
            ->shouldBeCalled();

        $command = new RefundCommand($order->reveal(), null, Refund::LIABLE_PARTY_PLATFORM, 'Lorem ipsum');

        call_user_func_array($this->handler, [ $command ]);

        $this->assertTrue($creditCard->hasRefunds());
        $this->assertTrue($conecs->hasRefunds());

        // getRefundTotal

        $this->assertEquals(Refund::LIABLE_PARTY_PLATFORM, $creditCard->getRefunds()[0]->getLiableParty());
        $this->assertEquals('Lorem ipsum', $creditCard->getRefunds()[0]->getComments());

        $this->assertEquals(2000, $creditCard->getRefundTotal());
        $this->assertEquals(500, $conecs->getRefundTotal());
    }
}
