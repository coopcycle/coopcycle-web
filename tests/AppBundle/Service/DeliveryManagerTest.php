<?php

namespace AppBundle\Service;

use AppBundle\BaseTest;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Service\DeliveryManager;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Prophecy\Argument;

class DeliveryManagerTest extends KernelTestCase
{
    private $expressionLanguage;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->expressionLanguage = static::$kernel->getContainer()->get('coopcycle.expression_language');
    }

    public function testGetPrice()
    {
        $rule1 = new PricingRule();
        $rule1->setExpression('distance in 0..3000');
        $rule1->setPrice(5.99);

        $rule2 = new PricingRule();
        $rule2->setExpression('distance in 3000..5000');
        $rule2->setPrice(6.99);

        $rule3 = new PricingRule();
        $rule3->setExpression('distance in 5000..7500');
        $rule3->setPrice(8.99);

        $ruleSet = new PricingRuleSet();
        $ruleSet->setRules(new ArrayCollection([
            $rule1,
            $rule2,
            $rule3,
        ]));

        $deliveryManager = new DeliveryManager(
            $this->expressionLanguage
        );

        $delivery = new Delivery();
        $delivery->setDistance(1500);

        $this->assertEquals(5.99, $deliveryManager->getPrice($delivery, $ruleSet));
    }
}
