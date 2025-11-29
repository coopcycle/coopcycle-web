<?php

namespace AppBundle\Pricing;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Sylius\Product\ProductVariantInterface;

class Result
{
    public ?Delivery $delivery = null;
    public ?Task $task = null;

    /**
     * @param RuleResult[] $ruleResults
     * @param ProductVariantInterface|null $productVariant
     */
    public function __construct(
        public readonly array $ruleResults,
        public readonly ?ProductVariantInterface $productVariant = null
    ) {
    }

    public function setDelivery(Delivery $delivery): void
    {
        $this->delivery = $delivery;
    }

    public function setTask(Task $task): void
    {
        $this->task = $task;
    }
}
