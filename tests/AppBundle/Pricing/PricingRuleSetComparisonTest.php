<?php

declare(strict_types=1);

namespace Tests\AppBundle\Pricing;

use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Pricing\PricingManager;
use PHPUnit\Framework\TestCase;

class PricingRuleSetComparisonTest extends TestCase
{
    private function createPricingRuleSet(int $id): PricingRuleSet
    {
        $pricingRuleSet = new PricingRuleSet();

        $property = new \ReflectionProperty(PricingRuleSet::class, 'id');
        $property->setValue($pricingRuleSet, $id);

        return $pricingRuleSet;
    }

    public function testSameRowAsTwoInstancesIsTheSame(): void
    {
        // i.e. one denormalized from a request, the other loaded with an order
        $this->assertTrue(PricingManager::isSamePricingRuleSet(
            $this->createPricingRuleSet(1),
            $this->createPricingRuleSet(1)
        ));
    }

    public function testDifferentRowsAreNotTheSame(): void
    {
        $this->assertFalse(PricingManager::isSamePricingRuleSet(
            $this->createPricingRuleSet(1),
            $this->createPricingRuleSet(2)
        ));
    }

    public function testNullIsOnlyTheSameAsNull(): void
    {
        $this->assertTrue(PricingManager::isSamePricingRuleSet(null, null));
        $this->assertFalse(PricingManager::isSamePricingRuleSet($this->createPricingRuleSet(1), null));
        $this->assertFalse(PricingManager::isSamePricingRuleSet(null, $this->createPricingRuleSet(1)));
    }
}
