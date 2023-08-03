<?php

namespace Tests\AppBundle\Pricing;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\Store;
use AppBundle\Pricing\PriceCalculator;
use AppBundle\Sylius\Order\OrderFactory;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Product\Factory\ProductFactoryInterface;
use Sylius\Component\Product\Factory\ProductVariantFactoryInterface;
use Sylius\Component\Product\Repository\ProductRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PriceCalculatorTest extends KernelTestCase
{
	protected function setUp(): void
    {
    	parent::setUp();

        self::bootKernel();

        // @see https://symfony.com/blog/new-in-symfony-4-1-simpler-service-testing
        $this->orderFactory = self::$container->get(OrderFactory::class);
        $this->expressionLanguage = self::$container->get('coopcycle.expression_language');
        $this->productVariantFactory = self::$container->get(ProductVariantFactoryInterface::class);

        $this->entityManager = self::$container->get(EntityManagerInterface::class);

        $channelFactory = self::$container->get('sylius.factory.channel');
        $channelRepository = self::$container->get('sylius.repository.channel');

        $taxCategoryFactory = self::$container->get('sylius.factory.tax_category');
        $taxCategoryRepository = self::$container->get('sylius.repository.tax_category');

        $this->productFactory = self::$container->get(ProductFactoryInterface::class);
        $this->productRepository = self::$container->get('sylius.repository.product');

        $purger = new ORMPurger($this->entityManager);
        $purger->purge();

        // Channels
        $channel = $channelFactory->createNamed('Web');
        $channel->setCode('web');
        $channelRepository->add($channel);

        // Taxes

        $taxCategory = $taxCategoryFactory->createNew();
        $taxCategory->setCode('SERVICE_TAX_EXEMPT');
        $taxCategory->setName('No taxes');
        $taxCategoryRepository->add($taxCategory);

        $product = $this->productFactory->createNew();
        $product->setCode('CPCCL-ODDLVR');
        $product->setEnabled(true);

        $this->productRepository->add($product);

        $this->orderItemFactory = self::$container->get('sylius.factory.order_item');
        $this->orderModifier = self::$container->get(OrderModifierInterface::class);
        $this->orderItemQuantityModifier = self::$container->get(OrderItemQuantityModifierInterface::class);

        $this->priceCalculator = new PriceCalculator(
            $this->orderFactory,
            $this->expressionLanguage,
            $this->productVariantFactory,
            $this->orderItemFactory,
            $this->orderModifier,
            $this->orderItemQuantityModifier
        );
    }

    public function testCreateOrder()
    {
    	$rule1 = new PricingRule();
        $rule1->setExpression('distance in 0..3000');
        $rule1->setPrice(599);

        $rule2 = new PricingRule();
        $rule2->setExpression('distance in 3000..5000');
        $rule2->setPrice(699);

        $rule3 = new PricingRule();
        $rule3->setExpression('distance in 5000..7500');
        $rule3->setPrice(899);

        $ruleSet = new PricingRuleSet();
        $ruleSet->setRules(new ArrayCollection([
            $rule1,
            $rule2,
            $rule3,
        ]));

        $store = new Store();
        $store->setPricingRuleSet($ruleSet);

        $delivery = new Delivery();
        $delivery->setStore($store);
        $delivery->setDistance(3500);

        $order = $this->priceCalculator->createOrder($delivery);

        $this->assertEquals(699, $order->getTotal());
    }
}
