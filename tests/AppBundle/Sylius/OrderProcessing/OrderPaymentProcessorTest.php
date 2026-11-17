<?php

namespace Tests\AppBundle\Sylius\OrderProcessing;

use AppBundle\Edenred\Client as EdenredClient;
use AppBundle\Entity\GiftCard\GiftCard;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\Payment;
use AppBundle\Payment\GatewayResolver;
use AppBundle\Service\GiftCard\GiftCardManager;
use AppBundle\Service\NullLoggingUtils;
use AppBundle\Service\PawapayManager;
use AppBundle\Service\PaygreenManager;
use AppBundle\Service\StripeManager;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\OrderProcessing\OrderPaymentProcessor;
use AppBundle\Sylius\Payment\Context as PaymentContext;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Sylius\Component\Payment\Factory\PaymentFactoryInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentMethod;
use Sylius\Component\Payment\Repository\PaymentMethodRepositoryInterface;

/**
 * The split a gift card produces.
 *
 * The invariant every one of these asserts, directly or by construction: a
 * gift card never changes the order total. It is a means of payment, not a
 * discount -- the restaurant's take and every tax adjustment have to come
 * out exactly as they would without it.
 */
class OrderPaymentProcessorTest extends TestCase
{
    use ProphecyTrait;

    private array $methods = [];

    public function setUp(): void
    {
        // Not a prophecy: findOneByCode() is magic on Sylius' repository
        // interface, so a double has no method to stub.
        $this->paymentMethodRepository = new class implements PaymentMethodRepositoryInterface {
            private array $methods = [];

            public function findOneByCode($code)
            {
                if (!isset($this->methods[$code])) {
                    $method = new PaymentMethod();
                    $method->setCode($code);
                    $this->methods[$code] = $method;
                }

                return $this->methods[$code];
            }

            public function findByName(string $name, string $locale): array { return []; }
            public function add(object $resource): void {}
            public function remove(object $resource): void {}
            public function find($id) { return null; }
            public function findAll(): array { return []; }
            public function findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null): array { return []; }
            public function findOneBy(array $criteria) { return null; }
            public function createPaginator(array $criteria = [], array $sorting = []): iterable { return []; }
            public function getClassName(): string { return PaymentMethod::class; }
        };
        $this->paymentFactory = $this->prophesize(PaymentFactoryInterface::class);
        $this->currencyContext = $this->prophesize(CurrencyContextInterface::class);
        $this->paymentContext = new PaymentContext();
        $this->edenredClient = $this->prophesize(EdenredClient::class);
        $this->stripeManager = $this->prophesize(StripeManager::class);
        $this->paygreenManager = $this->prophesize(PaygreenManager::class);
        $this->pawapayManager = $this->prophesize(PawapayManager::class);
        $this->gatewayResolver = $this->prophesize(GatewayResolver::class);
        $this->giftCardManager = $this->prophesize(GiftCardManager::class);

        $this->currencyContext->getCurrencyCode()->willReturn('EUR');
        $this->gatewayResolver->resolveForOrderAndMethod(Argument::cetera())->willReturn('stripe');

        $this->paymentFactory
            ->createWithAmountAndCurrencyCode(Argument::type('int'), Argument::type('string'))
            ->will(function ($args) {
                $payment = new Payment();
                $payment->setAmount($args[0]);
                $payment->setCurrencyCode($args[1]);

                return $payment;
            });

        $this->processor = new OrderPaymentProcessor(
            $this->paymentMethodRepository,
            $this->paymentFactory->reveal(),
            $this->currencyContext->reveal(),
            $this->paymentContext,
            $this->edenredClient->reveal(),
            $this->stripeManager->reveal(),
            $this->paygreenManager->reveal(),
            $this->pawapayManager->reveal(),
            $this->gatewayResolver->reveal(),
            $this->giftCardManager->reveal(),
            new NullLogger(),
            new NullLoggingUtils()
        );
    }

    private function createOrder(int $total, ?GiftCard $giftCard = null): Order
    {
        $order = new class($total) extends Order {
            public function __construct(private int $fakeTotal)
            {
                parent::__construct();
            }

            public function getTotal(): int
            {
                return $this->fakeTotal;
            }

            public function isFoodtech(): bool
            {
                return true;
            }
        };

        $order->setState(OrderInterface::STATE_CART);
        $order->setGiftCard($giftCard);

        return $order;
    }

    private function createGiftCard(int $balance): GiftCard
    {
        $giftCard = new GiftCard();
        $giftCard->setCode('ABCD2345EFGH');
        $giftCard->setCurrencyCode('EUR');
        $giftCard->setInitialAmount($balance);
        $giftCard->activate($balance);

        return $giftCard;
    }

    private function amountsByMethod(Order $order): array
    {
        $amounts = [];

        foreach ($order->getPayments() as $payment) {
            $amounts[$payment->getMethod()->getCode()] = $payment->getAmount();
        }

        return $amounts;
    }

    public function testNoGiftCardLeavesOneCardPayment()
    {
        $order = $this->createOrder(2450);
        $this->giftCardManager->getMaxAmount(Argument::cetera())->willReturn(0);

        $this->processor->process($order);

        $this->assertSame(['CARD' => 2450], $this->amountsByMethod($order));
        $this->assertSame(2450, $order->getTotal());
    }

    public function testPartialCoverageSplitsIntoTwoLegs()
    {
        $giftCard = $this->createGiftCard(2000);
        $order = $this->createOrder(2450, $giftCard);

        $this->giftCardManager->getMaxAmount(Argument::cetera())->willReturn(2000);

        $this->processor->process($order);

        $this->assertSame(['GIFT_CARD' => 2000, 'CARD' => 450], $this->amountsByMethod($order));
        $this->assertSame(2450, $order->getTotal(), 'A gift card must not change the order total');
        $this->assertSame(2000, $order->getGiftCardTotal());
        $this->assertTrue($order->hasGiftCardPayment());
    }

    public function testFullCoverageCreatesNoCardLeg()
    {
        $giftCard = $this->createGiftCard(5000);
        $order = $this->createOrder(2450, $giftCard);

        $this->giftCardManager->getMaxAmount(Argument::cetera())->willReturn(2450);

        $this->processor->process($order);

        $this->assertSame(['GIFT_CARD' => 2450], $this->amountsByMethod($order));
        $this->assertSame(2450, $order->getTotal());

        // A zero-amount card payment would be rejected by Stripe, and
        // configuring one would resolve a connected account for a charge
        // that will never happen.
        $this->stripeManager->configurePayment(Argument::any())->shouldNotHaveBeenCalled();
    }

    public function testGrowingTheGiftCardRemovesTheCardLeg()
    {
        $giftCard = $this->createGiftCard(2000);
        $order = $this->createOrder(2450, $giftCard);

        $this->giftCardManager->getMaxAmount(Argument::cetera())->willReturn(2000);
        $this->processor->process($order);
        $this->assertCount(2, $order->getPayments());

        // The customer applies a bigger card, or tops this one up.
        $this->giftCardManager->getMaxAmount(Argument::cetera())->willReturn(2450);
        $this->processor->process($order);

        $this->assertSame(['GIFT_CARD' => 2450], $this->amountsByMethod($order));
    }

    public function testShrinkingTheGiftCardBringsTheCardLegBack()
    {
        $giftCard = $this->createGiftCard(2450);
        $order = $this->createOrder(2450, $giftCard);

        $this->giftCardManager->getMaxAmount(Argument::cetera())->willReturn(2450);
        $this->processor->process($order);
        $this->assertCount(1, $order->getPayments());

        $this->giftCardManager->getMaxAmount(Argument::cetera())->willReturn(1000);
        $this->processor->process($order);

        $this->assertSame(['GIFT_CARD' => 1000, 'CARD' => 1450], $this->amountsByMethod($order));
    }

    public function testGiftCardCombinesWithEdenred()
    {
        $giftCard = $this->createGiftCard(1000);
        $order = $this->createOrder(2450, $giftCard);

        $this->paymentContext->setMethod('EDENRED');
        $this->giftCardManager->getMaxAmount(Argument::cetera())->willReturn(1000);
        // Edenred works out what it may cover from the whole order; it has
        // to be capped at what is actually left to pay.
        $this->edenredClient->getMaxAmount(Argument::any())->willReturn(2000);

        $this->processor->process($order);

        $amounts = $this->amountsByMethod($order);

        $this->assertSame(1000, $amounts['GIFT_CARD']);
        $this->assertSame(1450, $amounts['EDENRED']);
        $this->assertArrayNotHasKey('CARD', $amounts, 'Nothing is left for a card to pay');
        $this->assertSame(2450, array_sum($amounts), 'The legs must add up to the order');
    }

    public function testGiftCardWithEdenredAndACardComplement()
    {
        $giftCard = $this->createGiftCard(500);
        $order = $this->createOrder(2450, $giftCard);

        $this->paymentContext->setMethod('EDENRED');
        $this->giftCardManager->getMaxAmount(Argument::cetera())->willReturn(500);
        $this->edenredClient->getMaxAmount(Argument::any())->willReturn(1000);

        $this->processor->process($order);

        $amounts = $this->amountsByMethod($order);

        $this->assertSame(500, $amounts['GIFT_CARD']);
        $this->assertSame(1000, $amounts['EDENRED']);
        $this->assertSame(950, $amounts['CARD']);
        $this->assertSame(2450, array_sum($amounts));
    }

    public function testCashOnDeliveryPaysOnlyTheRemainder()
    {
        $giftCard = $this->createGiftCard(2000);
        $order = $this->createOrder(2450, $giftCard);

        $this->paymentContext->setMethod('CASH_ON_DELIVERY');
        $this->giftCardManager->getMaxAmount(Argument::cetera())->willReturn(2000);

        $this->processor->process($order);

        $this->assertSame(
            ['GIFT_CARD' => 2000, 'CASH_ON_DELIVERY' => 450],
            $this->amountsByMethod($order)
        );
    }
}
