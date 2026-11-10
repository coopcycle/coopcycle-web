<?php

namespace Tests\AppBundle\Payment;

use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\Payment;
use AppBundle\Payment\GatewayResolver;
use AppBundle\Payment\StripeAvailability;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Sylius\Component\Payment\Model\PaymentMethod;

class GatewayResolverTest extends TestCase
{
    use ProphecyTrait;

    private $stripeAvailability;

    public function setUp(): void
    {
        $this->stripeAvailability = $this->prophesize(StripeAvailability::class);
    }

    private function createResolver(string $country = 'fr'): GatewayResolver
    {
        return new GatewayResolver(
            $country,
            ['ar', 'br'],
            $forceStripe = false,
            $paygreenEnabled = true,
            $this->stripeAvailability->reveal()
        );
    }

    private function createOrder(bool $usePaygreen): Order
    {
        $restaurant = new Restaurant();
        if ($usePaygreen) {
            $restaurant->setPaygreenShopId('sh_12345678');
            $restaurant->setPaymentGateway('paygreen');
        }

        $order = new Order();
        $order->setRestaurant($restaurant);

        return $order;
    }

    public function testPaygreenOnlyShopUsesPaygreenForCards()
    {
        $order = $this->createOrder($usePaygreen = true);

        $this->stripeAvailability
            ->supportsRestaurant($order->getRestaurant())
            ->willReturn(false);

        $this->assertEquals('paygreen', $this->createResolver()->resolveForOrder($order));
    }

    public function testShopWithBothGatewaysUsesStripeForCards()
    {
        $order = $this->createOrder($usePaygreen = true);

        $this->stripeAvailability
            ->supportsRestaurant($order->getRestaurant())
            ->willReturn(true);

        $this->assertEquals('stripe', $this->createResolver()->resolveForOrder($order));
    }

    public function testShopWithoutPaygreenUsesStripeForCards()
    {
        $order = $this->createOrder($usePaygreen = false);

        $this->stripeAvailability
            ->supportsRestaurant($order->getRestaurant())
            ->willReturn(true);

        $this->assertEquals('stripe', $this->createResolver()->resolveForOrder($order));
    }

    public function testStripeIsNotUsedWhenCountryUsesAnotherGateway()
    {
        $order = $this->createOrder($usePaygreen = true);

        $this->stripeAvailability
            ->supportsRestaurant($order->getRestaurant())
            ->willReturn(true);

        $this->assertEquals('paygreen', $this->createResolver('br')->resolveForOrder($order));
    }

    public function testWalletMethodsAlwaysUsePaygreen()
    {
        $order = $this->createOrder($usePaygreen = true);

        $this->stripeAvailability
            ->supportsRestaurant($order->getRestaurant())
            ->willReturn(true);

        $resolver = $this->createResolver();

        // Apple Pay & Google Pay are only supported through Paygreen,
        // they reach the "CARD" branch of OrderPaymentProcessor
        $this->assertEquals('paygreen', $resolver->resolveForOrderAndMethod($order, 'APPLE_PAY'));
        $this->assertEquals('paygreen', $resolver->resolveForOrderAndMethod($order, 'GOOGLE_PAY'));
        $this->assertEquals('paygreen', $resolver->resolveForOrderAndMethod($order, 'CONECS'));

        $this->assertEquals('stripe', $resolver->resolveForOrderAndMethod($order, 'CARD'));
        $this->assertEquals('stripe', $resolver->resolveForOrderAndMethod($order, null));
    }

    public function testMealVoucherPaymentAlwaysUsesPaygreen()
    {
        $order = $this->createOrder($usePaygreen = true);

        $this->stripeAvailability
            ->supportsRestaurant($order->getRestaurant())
            ->willReturn(true);

        $method = new PaymentMethod();
        $method->setCode('CONECS');

        $payment = new Payment();
        $payment->setMethod($method);
        $order->addPayment($payment);

        $this->assertEquals('paygreen', $this->createResolver()->resolveForPayment($payment));
    }

    public function testCardPaymentOnPaygreenShopWithStripeUsesStripe()
    {
        $order = $this->createOrder($usePaygreen = true);

        $this->stripeAvailability
            ->supportsRestaurant($order->getRestaurant())
            ->willReturn(true);

        $method = new PaymentMethod();
        $method->setCode('CARD');

        $payment = new Payment();
        $payment->setMethod($method);
        $order->addPayment($payment);

        $this->assertEquals('stripe', $this->createResolver()->resolveForPayment($payment));
    }

    public function testExistingPaygreenPaymentKeepsUsingPaygreen()
    {
        $order = $this->createOrder($usePaygreen = true);

        $this->stripeAvailability
            ->supportsRestaurant($order->getRestaurant())
            ->willReturn(true);

        $method = new PaymentMethod();
        $method->setCode('CARD');

        $payment = new Payment();
        $payment->setMethod($method);
        $payment->setPaygreenPaymentOrderId('po_12345678');
        $order->addPayment($payment);

        $this->assertEquals('paygreen', $this->createResolver()->resolveForPayment($payment));
    }
}
