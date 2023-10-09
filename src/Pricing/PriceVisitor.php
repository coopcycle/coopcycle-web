<?php

namespace AppBundle\Pricing;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Sylius\Order\OrderInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Product\Factory\ProductVariantFactoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

class PriceVisitor
{
	private $matchedRules = [];

	public function __construct(
		private Delivery $delivery,
		private OrderInterface $order,
		private ExpressionLanguage $expressionLanguage,
		private ProductVariantFactoryInterface $productVariantFactory,
		private FactoryInterface $orderItemFactory,
		private OrderModifierInterface $orderModifier,
		private OrderItemQuantityModifierInterface $orderItemQuantityModifier)
	{
	}

	public function getMatchedRules(): array
	{
		return $this->matchedRules;
	}

	public function getOrder(): OrderInterface
	{
		return $this->order;
	}

	public function visit(PricingRule $rule)
	{
		if ($rule->matches($this->delivery, $this->expressionLanguage)) {

			$this->matchedRules[] = $rule;

			$price = $rule->evaluatePrice($this->delivery, $this->expressionLanguage);

			if (is_numeric($rule->getPrice())) {

				$variant = $this->productVariantFactory->createForPricingRule($rule, $price, $this->expressionLanguage);

				$orderItem = $this->orderItemFactory->createNew();
		        $orderItem->setVariant($variant);
		        $orderItem->setUnitPrice($variant->getPrice());

		        $this->orderItemQuantityModifier->modify($orderItem, 1);

		        $this->orderModifier->addToOrder($this->order, $orderItem);

			} else {

				$parsedExpression = $this->expressionLanguage->parse($rule->getPrice(), [
		            'distance',
		            'weight',
		            'vehicle',
		            'pickup',
		            'dropoff',
		            'packages',
		            'order',
		        ]);

		        $nodes = $parsedExpression->getNodes();

		        if ($nodes->attributes['name'] === 'price_range') {

		        	$name          = $nodes->nodes['arguments']->nodes[0]->attributes['name'];
		        	$pricePerRange = $nodes->nodes['arguments']->nodes[1]->attributes['value'];
		        	$size          = $nodes->nodes['arguments']->nodes[2]->attributes['value'];
		        	$over          = $nodes->nodes['arguments']->nodes[3]->attributes['value'];

		        	$value = $this->expressionLanguage->evaluate($name, Delivery::toExpressionLanguageValues($this->delivery));

		        	$quantity = (int) ceil(($value - $over) / $size);

		        	$variant =
		        		$this->productVariantFactory->createForPricingRulePriceRange($pricePerRange, $size, $this->expressionLanguage);

		        	$orderItem = $this->orderItemFactory->createNew();
			        $orderItem->setVariant($variant);
			        $orderItem->setUnitPrice($variant->getPrice());

			        $this->orderItemQuantityModifier->modify($orderItem, $quantity);

			        $this->orderModifier->addToOrder($this->order, $orderItem);

		        }

				// var_dump('price is a formula');
			}
		}
	}
}
