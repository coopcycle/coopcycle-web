<?php

namespace Tests\AppBundle\Service;

use AppBundle\Entity\Address;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Entity\Sylius\Payment;
use AppBundle\Service\PaygreenManager;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\Defaults;
use Doctrine\Common\Collections\ArrayCollection;
use Hashids\Hashids;
use PHPUnit\Framework\TestCase;
use Paygreen\Sdk\Payment\V3\Client as PaygreenClient;
use Paygreen\Sdk\Payment\V3\Model\Buyer;
use Paygreen\Sdk\Payment\V3\Model\PaymentOrder;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Model\Channel;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Sylius\Component\Payment\Factory\PaymentFactoryInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentMethod;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Payment\Repository\PaymentMethodRepositoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PaygreenManagerTest extends TestCase
{
    use ProphecyTrait;

    private $paygreenClient;
    private $orderNumberAssigner;

    public function setUp(): void
    {
        $this->paygreenClient = $this->prophesize(PaygreenClient::class);
        $this->orderNumberAssigner = $this->prophesize(OrderNumberAssignerInterface::class);
        $this->hashids8 = new Hashids();
        $this->paymentMethodRepository = $this->prophesize(PaymentMethodRepositoryInterface::class);
        $this->paymentFactory = $this->prophesize(PaymentFactoryInterface::class);
        $this->urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $this->channelContext = $this->prophesize(ChannelContextInterface::class);
        $this->adjustmentFactory = $this->prophesize(AdjustmentFactoryInterface::class);
        $this->defaults = $this->prophesize(Defaults::class);

        $channel = new Channel();
        $channel->setCode('web');

        $this->channelContext->getChannel()->willReturn($channel);
        $this->urlGenerator->generate(Argument::type('string'), [], Argument::type('int'))->willReturn('https://demo.coopcycle.org');

        $this->paygreenManager = new PaygreenManager(
            $this->paygreenClient->reveal(),
            $this->orderNumberAssigner->reveal(),
            $this->hashids8,
            $this->paymentMethodRepository->reveal(),
            $this->paymentFactory->reveal(),
            $this->urlGenerator->reveal(),
            $this->channelContext->reveal(),
            $this->adjustmentFactory->reveal(),
            $this->defaults->reveal(),
            'fr'
        );
    }

    private function createResponseProphecy(array $data, int $statusCode = 200)
    {
        $body = $this->prophesize(StreamInterface::class);
        $body->getContents()->willReturn(json_encode(['data' => $data ]));

        $response = $this->prophesize(ResponseInterface::class);
        $response->getBody()->willReturn($body->reveal());
        $response->getStatusCode()->willReturn($statusCode);

        return $response;
    }

    public function testCapture()
    {
        $order = $this->prophesize(OrderInterface::class);

        $payment = $this->prophesize(Payment::class);
        $payment->getPaygreenPaymentOrderId()->willReturn('po_123456');
        $payment->getOrder()->willReturn($order->reveal());

        $body = $this->prophesize(StreamInterface::class);
        $body->getContents()->willReturn('{"data":{"token":"123456"}}');

        $response = $this->prophesize(ResponseInterface::class);
        $response->getBody()->willReturn($body->reveal());

        $this->paygreenClient->authenticate()->willReturn($response->reveal());

        $this->paygreenClient
            ->getPaymentOrder('po_123456')
            ->willReturn($this->createResponseProphecy([
                'id' => 'po_123456',
                'status' => 'payment_order.authorized',
            ])->reveal());

        $this->paygreenClient
            ->capturePaymentOrder('po_123456')
            ->willReturn($this->createResponseProphecy([
                'id' => 'po_123456',
                'status' => 'payment_order.authorized',
                'transactions' => [
                    [
                        'id' => 'tr_123456',
                        'status' => 'transaction.captured',
                        'operations' => [
                            [
                                'status' => 'operation.captured',
                                'fees' => 16,
                                'cost' => 17,
                            ]
                        ]
                    ]
                ]
            ])->reveal());

        $this->paygreenClient->setBearer('123456')->shouldBeCalled();

        $adjustment = $this->prophesize(AdjustmentInterface::class);

        $this->adjustmentFactory->createWithData(
            AdjustmentInterface::STRIPE_FEE_ADJUSTMENT,
            'Paygreen fee',
            (16 + 17),
            true
        )->willReturn($adjustment->reveal());

        $order->removeAdjustments(AdjustmentInterface::STRIPE_FEE_ADJUSTMENT)->shouldBeCalled();
        $order->addAdjustment($adjustment->reveal())->shouldBeCalled();

        $this->paygreenManager->capture($payment->reveal());
    }

    public function testCreatePaymentOrder()
    {
        $address = new Address();
        $address->setAddressLocality('Paris');
        $address->setPostalCode('75000');

        $customer = new Customer();

        $restaurant = new LocalBusiness();
        $restaurant->setPaygreenShopId('sh_123456');

        $order = $this->prophesize(OrderInterface::class);
        $order->getId()->willReturn(1);
        $order->getShippingAddress()->willReturn($address);
        $order->getCustomer()->willReturn($customer);
        $order->getRestaurant()->willReturn($restaurant);
        $order->getTotal()->willReturn(3000);
        $order->getAdjustmentsTotal(AdjustmentInterface::DELIVERY_ADJUSTMENT)->willReturn(300);
        $order->getAdjustmentsTotal(AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT)->willReturn(0);
        $order->getAdjustmentsTotal(AdjustmentInterface::TIP_ADJUSTMENT)->willReturn(0);
        $order->getAlcoholicItemsTotal()->willReturn(0);
        $order->getFeeTotal()->willReturn(300);
        $order->getNumber()->willReturn('A1');

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setCode('CARD');

        $payment = $this->prophesize(Payment::class);
        $payment->getPaygreenPaymentOrderId()->willReturn('po_123456');
        $payment->getOrder()->willReturn($order->reveal());
        $payment->getMethod()->willReturn($paymentMethod);
        $payment->getCurrencyCode()->willReturn('EUR');

        $body = $this->prophesize(StreamInterface::class);
        $body->getContents()->willReturn('{"data":{"token":"123456"}}');

        $response = $this->prophesize(ResponseInterface::class);
        $response->getBody()->willReturn($body->reveal());

        $this->paygreenClient->authenticate()->willReturn($response->reveal());

        $this->paygreenClient
            ->createBuyer(Argument::type(Buyer::class))
            ->willReturn($this->createResponseProphecy([
                'id' => 'bu_123456',
            ])->reveal());

        $this->paygreenClient
            ->createPaymentOrder(Argument::that(function (PaymentOrder $paymentOrder) {

                $this->assertEquals($paymentOrder->getEligibleAmounts(), [
                    'food' => 2700,
                    'ecommerce' => 300,
                ]);
                $this->assertEmpty($paymentOrder->getReference());
                $this->assertFalse($paymentOrder->isAutoCapture());

                return true;
            }))
            ->willReturn($this->createResponseProphecy([
                'id' => 'po_123456',
                'status' => 'payment_order.pending',
                'object_secret' => '123456',
                'hosted_payment_url' => 'https://paygreen.fr'
            ])->reveal());

        $this->paygreenClient->setBearer('123456')->shouldBeCalled();

        $this->paygreenManager->createPaymentOrder($payment->reveal());

        $payment->setPaygreenObjectSecret('123456')->shouldHaveBeenCalled();
        $payment->setPaygreenHostedPaymentUrl('https://paygreen.fr')->shouldHaveBeenCalled();
        $payment->setPaygreenPaymentOrderId('po_123456')->shouldHaveBeenCalled();
    }
}
