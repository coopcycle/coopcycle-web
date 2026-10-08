<?php

declare(strict_types=1);

namespace Tests\AppBundle\Service;

use AppBundle\Action\Incident\CreateIncident;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\Sylius\CalculateUsingPricingRules;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\Product;
use AppBundle\Entity\Sylius\ProductRepository;
use AppBundle\Entity\Task;
use AppBundle\Entity\Task\RecurrenceRule;
use AppBundle\Pricing\PricingManager;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\DeliveryOrderManager;
use AppBundle\Service\OrderManager;
use AppBundle\Sylius\Order\OrderFactory;
use AppBundle\Sylius\Product\ProductVariantInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * An order generated from a recurrence rule is found again for that rule and date
 * by its subscription, which is how a retry skips the rules that already produced
 * an order. An order written without it is invisible to the retry, which creates
 * it again.
 */
class DeliveryOrderManagerTest extends TestCase
{
    use ProphecyTrait;

    private $entityManager;
    private $deliveryManager;
    private $orderManager;
    private $orderFactory;
    private $pricingManager;
    private $productRepository;

    private DeliveryOrderManager $deliveryOrderManager;

    public function setUp(): void
    {
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);
        $this->deliveryManager = $this->prophesize(DeliveryManager::class);
        $this->orderManager = $this->prophesize(OrderManager::class);
        $this->orderFactory = $this->prophesize(OrderFactory::class);
        $this->pricingManager = $this->prophesize(PricingManager::class);
        $this->productRepository = $this->prophesize(ProductRepository::class);

        $product = $this->prophesize(Product::class);
        $product->getOptions()->willReturn(new ArrayCollection());
        $this->productRepository->findOnDemandDeliveryProduct()->willReturn($product->reveal());

        $this->pricingManager
            ->getProductVariantsWithPricingStrategy(Argument::type(Delivery::class), Argument::any())
            ->willReturn([$this->prophesize(ProductVariantInterface::class)->reveal()]);
        $this->pricingManager
            ->processDeliveryOrder(Argument::any(), Argument::type('array'))
            ->shouldBeCalled();

        $this->deliveryOrderManager = new DeliveryOrderManager(
            $this->prophesize(Security::class)->reveal(),
            $this->entityManager->reveal(),
            $this->prophesize(TranslatorInterface::class)->reveal(),
            $this->productRepository->reveal(),
            $this->deliveryManager->reveal(),
            $this->orderManager->reveal(),
            $this->orderFactory->reveal(),
            $this->pricingManager->reveal(),
            $this->prophesize(CreateIncident::class)->reveal(),
            false,
        );
    }

    private function createRecurrenceRule(?PricingRuleSet $pricingRuleSet = null): RecurrenceRule
    {
        $recurrenceRule = $this->prophesize(RecurrenceRule::class);
        $recurrenceRule->getArbitraryPriceTemplate()->willReturn(null);
        $recurrenceRule->getPricingRuleSet()->willReturn($pricingRuleSet);

        return $recurrenceRule->reveal();
    }

    private function expectDeliveryAndOrder(RecurrenceRule $recurrenceRule): Order
    {
        $delivery = Delivery::createWithTasks(new Task(), new Task());
        $order = new Order();

        $this->deliveryManager
            ->createDeliveryFromRecurrenceRule($recurrenceRule, '2026-10-06', true)
            ->willReturn($delivery);

        $this->orderFactory->createForDelivery($delivery)->willReturn($order);

        return $order;
    }

    public function testCreateOrderFromRecurrenceRule()
    {
        $recurrenceRule = $this->createRecurrenceRule();
        $order = $this->expectDeliveryAndOrder($recurrenceRule);

        $this->entityManager->persist($order)->shouldBeCalled();
        $this->entityManager->flush()->shouldBeCalled();
        $this->orderManager->onDemand($order)->shouldBeCalled();

        $result = $this->deliveryOrderManager->createOrderFromRecurrenceRule($recurrenceRule, '2026-10-06');

        $this->assertSame($order, $result);
        $this->assertSame($recurrenceRule, $order->getSubscription());
    }

    public function testOrderIsPricedWithTheRuleSetChosenOnItsRule()
    {
        $pricingRuleSet = new PricingRuleSet();

        $recurrenceRule = $this->createRecurrenceRule($pricingRuleSet);
        $order = $this->expectDeliveryAndOrder($recurrenceRule);

        $this->pricingManager
            ->getProductVariantsWithPricingStrategy(
                Argument::type(Delivery::class),
                Argument::that(fn ($strategy) => $strategy instanceof CalculateUsingPricingRules
                    && $strategy->pricingRuleSet === $pricingRuleSet)
            )
            ->willReturn([$this->prophesize(ProductVariantInterface::class)->reveal()])
            ->shouldBeCalled();

        // So that a recalculation of the order keeps it
        $this->pricingManager
            ->setChosenPricingRuleSet($order, Argument::type(Delivery::class), $pricingRuleSet)
            ->shouldBeCalled();

        $this->deliveryOrderManager->createOrderFromRecurrenceRule($recurrenceRule, '2026-10-06');
    }

    public function testOrderIsLinkedToItsRuleWhenFirstWritten()
    {
        $recurrenceRule = $this->createRecurrenceRule();
        $order = $this->expectDeliveryAndOrder($recurrenceRule);

        $subscriptionsAtFlush = [];

        $this->entityManager->persist($order)->shouldBeCalled();
        $this->entityManager->flush()
            ->will(function () use ($order, &$subscriptionsAtFlush) {
                $subscriptionsAtFlush[] = $order->getSubscription();
            });

        // Anything failing once the order is written: on lcr, the worker ran out
        // of memory publishing the live updates of the new tasks.
        $this->orderManager->onDemand($order)
            ->willThrow(new \RuntimeException('Allowed memory size exhausted'));

        try {
            $this->deliveryOrderManager->createOrderFromRecurrenceRule($recurrenceRule, '2026-10-06');
            $this->fail('The exception should not be swallowed');
        } catch (\RuntimeException $e) {
            $this->assertEquals('Allowed memory size exhausted', $e->getMessage());
        }

        $this->assertSame([$recurrenceRule], $subscriptionsAtFlush);
    }
}
