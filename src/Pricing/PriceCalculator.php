<?php

namespace AppBundle\Pricing;

use AppBundle\Entity\Delivery;
use AppBundle\Pricing\PriceVisitor;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Order\OrderFactory;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Product\Factory\ProductVariantFactoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

class PriceCalculator
{
	public function __construct(
		private OrderFactory $orderFactory,
		private ExpressionLanguage $expressionLanguage,
		private ProductVariantFactoryInterface $productVariantFactory,
		private FactoryInterface $orderItemFactory,
		private OrderModifierInterface $orderModifier,
		private OrderItemQuantityModifierInterface $orderItemQuantityModifier)
	{

	}

	public function visit(Delivery $delivery): PriceVisitor
	{
		$order = $this->orderFactory->createNew();

		$store = $delivery->getStore();

		if (null === $store) {
			// TODO Throw Exception
		}

		$ruleSet = $store->getPricingRuleSet();

		$visitor = new PriceVisitor(
			$delivery,
			$order,
			$this->expressionLanguage,
			$this->productVariantFactory,
			$this->orderItemFactory,
			$this->orderModifier,
			$this->orderItemQuantityModifier
		);

		// TODO Do differently depending on strategy (map/find)

		// if ($ruleSet->getStrategy() === 'map') {
            foreach ($ruleSet->getRules() as $rule) {
            	$rule->accept($visitor);
            }
        // }

		return $visitor;
	}
}
