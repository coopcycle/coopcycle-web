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

			$variant = $this->productVariantFactory->createForPricingRule($rule, $price, $this->expressionLanguage);

			$orderItem = $this->orderItemFactory->createNew();
	        $orderItem->setVariant($variant);
	        $orderItem->setUnitPrice($variant->getPrice());

	        $this->orderItemQuantityModifier->modify($orderItem, 1);

	        $this->orderModifier->addToOrder($this->order, $orderItem);
		}
	}
}
