<?php

namespace Tests\AppBundle\Payment;

use AppBundle\Edenred\Authentication as EdenredAuthentication;
use AppBundle\Edenred\Client as EdenredClient;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Payment\GatewayResolver;
use AppBundle\Payment\PaymentMethodsResolver;
use AppBundle\Service\PaygreenManager;
use AppBundle\Service\SettingsManager;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Sylius\Component\Payment\Model\PaymentMethod;
use Sylius\Component\Payment\Repository\PaymentMethodRepositoryInterface;

class PaymentMethodsResolverTest extends TestCase
{
    use ProphecyTrait;

    public function testResolveForCheckoutIncludesCashWhenRestaurantSupportsEvenIfDisabled(): void
    {
        $settingsManager = $this->prophesize(SettingsManager::class);
        $gatewayResolver = $this->prophesize(GatewayResolver::class);
        $paygreenManager = $this->prophesize(PaygreenManager::class);
        $paymentMethodRepository = $this->prophesize(PaymentMethodRepositoryInterface::class);
        $edenredAuthentication = $this->prophesize(EdenredAuthentication::class);
        $edenredClient = $this->prophesize(EdenredClient::class);

        $order = $this->prophesize(Order::class);
        $order->supportsCashOnDelivery()->willReturn(true);
        $order->supportsEdenred()->willReturn(false);
        $order->isMultiVendor()->willReturn(false);
        $order->getRestaurant()->willReturn(null);

        $settingsManager->supportsCardPayments()->willReturn(false);
        $gatewayResolver->resolveForOrder($order->reveal())->willReturn('stripe');

        $cash = new PaymentMethod();
        $cash->setCode('CASH_ON_DELIVERY');
        $cash->setEnabled(false);
        $paymentMethodRepository->findOneBy(['code' => 'CASH_ON_DELIVERY'])->willReturn($cash);

        $resolver = new PaymentMethodsResolver(
            $settingsManager->reveal(),
            $gatewayResolver->reveal(),
            $paygreenManager->reveal(),
            $paymentMethodRepository->reveal(),
            $edenredAuthentication->reveal(),
            $edenredClient->reveal(),
            cashEnabled: false,
            edenredEnabled: false
        );

        $methods = $resolver->resolveForCheckout($order->reveal());

        $this->assertCount(1, $methods);
        $this->assertSame('cash_on_delivery', $methods[0]->getType());
    }

    public function testResolveForCheckoutDoesNotIncludeEdenredWhenDisabledGlobally(): void
    {
        $settingsManager = $this->prophesize(SettingsManager::class);
        $gatewayResolver = $this->prophesize(GatewayResolver::class);
        $paygreenManager = $this->prophesize(PaygreenManager::class);
        $paymentMethodRepository = $this->prophesize(PaymentMethodRepositoryInterface::class);
        $edenredAuthentication = $this->prophesize(EdenredAuthentication::class);
        $edenredClient = $this->prophesize(EdenredClient::class);

        $order = $this->prophesize(Order::class);
        $order->supportsCashOnDelivery()->willReturn(false);
        $order->supportsEdenred()->willReturn(true);
        $order->isMultiVendor()->willReturn(false);
        $order->getRestaurant()->willReturn(null);

        $settingsManager->supportsCardPayments()->willReturn(false);
        $gatewayResolver->resolveForOrder($order->reveal())->willReturn('stripe');

        $resolver = new PaymentMethodsResolver(
            $settingsManager->reveal(),
            $gatewayResolver->reveal(),
            $paygreenManager->reveal(),
            $paymentMethodRepository->reveal(),
            $edenredAuthentication->reveal(),
            $edenredClient->reveal(),
            cashEnabled: false,
            edenredEnabled: false
        );

        $this->assertSame([], $resolver->resolveForApi($order->reveal()));
        $this->assertSame([], $resolver->resolveForCheckout($order->reveal()));
    }

    public function testResolveForCheckoutIncludesEdenredWhenNotConnected(): void
    {
        $settingsManager = $this->prophesize(SettingsManager::class);
        $gatewayResolver = $this->prophesize(GatewayResolver::class);
        $paygreenManager = $this->prophesize(PaygreenManager::class);
        $paymentMethodRepository = $this->prophesize(PaymentMethodRepositoryInterface::class);
        $edenredAuthentication = $this->prophesize(EdenredAuthentication::class);
        $edenredClient = $this->prophesize(EdenredClient::class);

        $customer = $this->prophesize(Customer::class);

        $order = $this->prophesize(Order::class);
        $order->supportsCashOnDelivery()->willReturn(false);
        $order->supportsEdenred()->willReturn(true);
        $order->isMultiVendor()->willReturn(false);
        $order->getRestaurant()->willReturn(null);
        $order->getCustomer()->willReturn($customer->reveal());

        $settingsManager->supportsCardPayments()->willReturn(false);
        $gatewayResolver->resolveForOrder($order->reveal())->willReturn('stripe');

        $edenredAuthentication->getAuthorizeUrl($order->reveal())->willReturn('https://example.test/edenred');
        $edenredClient->hasValidCredentials($customer->reveal())->willReturn(false);

        $edenred = new PaymentMethod();
        $edenred->setCode('EDENRED');
        $edenred->setEnabled(true);
        $paymentMethodRepository->findOneBy(['code' => 'EDENRED'])->willReturn($edenred);

        $resolver = new PaymentMethodsResolver(
            $settingsManager->reveal(),
            $gatewayResolver->reveal(),
            $paygreenManager->reveal(),
            $paymentMethodRepository->reveal(),
            $edenredAuthentication->reveal(),
            $edenredClient->reveal(),
            cashEnabled: false,
            edenredEnabled: true
        );

        $methods = $resolver->resolveForCheckout($order->reveal());

        $this->assertCount(1, $methods);
        $this->assertSame('edenred', $methods[0]->getType());
        $this->assertSame([
            'data-edenred-is-connected' => false,
            'data-edenred-authorize-url' => 'https://example.test/edenred',
        ], $methods[0]->getChoiceAttr());
    }

    public function testResolveForCheckoutDoesNotIncludeEdenredWhenConnectedButAmountIsZero(): void
    {
        $settingsManager = $this->prophesize(SettingsManager::class);
        $gatewayResolver = $this->prophesize(GatewayResolver::class);
        $paygreenManager = $this->prophesize(PaygreenManager::class);
        $paymentMethodRepository = $this->prophesize(PaymentMethodRepositoryInterface::class);
        $edenredAuthentication = $this->prophesize(EdenredAuthentication::class);
        $edenredClient = $this->prophesize(EdenredClient::class);

        $customer = $this->prophesize(Customer::class);

        $order = $this->prophesize(Order::class);
        $order->supportsCashOnDelivery()->willReturn(false);
        $order->supportsEdenred()->willReturn(true);
        $order->isMultiVendor()->willReturn(false);
        $order->getRestaurant()->willReturn(null);
        $order->getCustomer()->willReturn($customer->reveal());

        $settingsManager->supportsCardPayments()->willReturn(false);
        $gatewayResolver->resolveForOrder($order->reveal())->willReturn('stripe');

        $edenredAuthentication->getAuthorizeUrl($order->reveal())->willReturn('https://example.test/edenred');
        $edenredClient->hasValidCredentials($customer->reveal())->willReturn(true);
        $edenredClient->getMaxAmount($order->reveal())->willReturn(0);

        $edenred = new PaymentMethod();
        $edenred->setCode('EDENRED');
        $edenred->setEnabled(true);
        $paymentMethodRepository->findOneBy(['code' => 'EDENRED'])->willReturn($edenred);

        $resolver = new PaymentMethodsResolver(
            $settingsManager->reveal(),
            $gatewayResolver->reveal(),
            $paygreenManager->reveal(),
            $paymentMethodRepository->reveal(),
            $edenredAuthentication->reveal(),
            $edenredClient->reveal(),
            cashEnabled: false,
            edenredEnabled: true
        );

        $this->assertSame([], $resolver->resolveForCheckout($order->reveal()));
    }

    public function testResolvePaygreenWalletsAreApiOnly(): void
    {
        $settingsManager = $this->prophesize(SettingsManager::class);
        $gatewayResolver = $this->prophesize(GatewayResolver::class);
        $paygreenManager = $this->prophesize(PaygreenManager::class);
        $paymentMethodRepository = $this->prophesize(PaymentMethodRepositoryInterface::class);
        $edenredAuthentication = $this->prophesize(EdenredAuthentication::class);
        $edenredClient = $this->prophesize(EdenredClient::class);

        $restaurant = $this->prophesize(LocalBusiness::class);
        $restaurant->getPaygreenShopId()->willReturn('shop_123');

        $order = $this->prophesize(Order::class);
        $order->supportsCashOnDelivery()->willReturn(false);
        $order->supportsEdenred()->willReturn(false);
        $order->isMultiVendor()->willReturn(false);
        $order->getRestaurant()->willReturn($restaurant->reveal());

        $settingsManager->supportsCardPayments()->willReturn(false);
        $gatewayResolver->resolveForOrder($order->reveal())->willReturn('paygreen');

        $paygreenManager->getEnabledPlatforms('shop_123')->willReturn([
            'restoflash',
            'conecs',
            'swile',
            'apple_pay',
            'google_pay',
        ]);

        $paymentMethodRepository
            ->findOneBy(Argument::type('array'))
            ->will(function (array $args) {
                $criteria = $args[0];
                $code = $criteria['code'] ?? null;
                if (null === $code) {
                    return null;
                }
                $method = new PaymentMethod();
                $method->setCode($code);
                $method->setEnabled(true);
                return $method;
            });

        $resolver = new PaymentMethodsResolver(
            $settingsManager->reveal(),
            $gatewayResolver->reveal(),
            $paygreenManager->reveal(),
            $paymentMethodRepository->reveal(),
            $edenredAuthentication->reveal(),
            $edenredClient->reveal(),
            cashEnabled: false,
            edenredEnabled: false
        );

        $apiTypes = $resolver->resolveForApi($order->reveal());
        $checkoutMethods = $resolver->resolveForCheckout($order->reveal());
        $checkoutTypes = array_map(fn ($m) => $m->getType(), $checkoutMethods);

        $this->assertContains('apple_pay', $apiTypes);
        $this->assertContains('google_pay', $apiTypes);

        $this->assertNotContains('apple_pay', $checkoutTypes);
        $this->assertNotContains('google_pay', $checkoutTypes);
    }

    public function testPawapayWithoutRestaurantEnablementExcludesCard(): void
    {
        $settingsManager = $this->prophesize(SettingsManager::class);
        $gatewayResolver = $this->prophesize(GatewayResolver::class);
        $paygreenManager = $this->prophesize(PaygreenManager::class);
        $paymentMethodRepository = $this->prophesize(PaymentMethodRepositoryInterface::class);
        $edenredAuthentication = $this->prophesize(EdenredAuthentication::class);
        $edenredClient = $this->prophesize(EdenredClient::class);

        $restaurant = $this->prophesize(LocalBusiness::class);
        $restaurant->isPawapayEnabled()->willReturn(false);

        $order = $this->prophesize(Order::class);
        $order->supportsCashOnDelivery()->willReturn(true);
        $order->supportsEdenred()->willReturn(false);
        $order->isMultiVendor()->willReturn(false);
        $order->getRestaurant()->willReturn($restaurant->reveal());

        $settingsManager->supportsCardPayments()->willReturn(true);
        $gatewayResolver->resolveForOrder($order->reveal())->willReturn('pawapay');

        $card = new PaymentMethod();
        $card->setCode('CARD');
        $card->setEnabled(true);

        $cash = new PaymentMethod();
        $cash->setCode('CASH_ON_DELIVERY');
        $cash->setEnabled(true);

        $paymentMethodRepository->findOneBy(['code' => 'CARD'])->willReturn($card);
        $paymentMethodRepository->findOneBy(['code' => 'CASH_ON_DELIVERY'])->willReturn($cash);

        $resolver = new PaymentMethodsResolver(
            $settingsManager->reveal(),
            $gatewayResolver->reveal(),
            $paygreenManager->reveal(),
            $paymentMethodRepository->reveal(),
            $edenredAuthentication->reveal(),
            $edenredClient->reveal(),
            cashEnabled: false,
            edenredEnabled: false
        );

        $this->assertSame(['cash_on_delivery'], $resolver->resolveForApi($order->reveal()));
        $this->assertSame(['cash_on_delivery'], array_map(fn ($m) => $m->getType(), $resolver->resolveForCheckout($order->reveal())));
    }
}
