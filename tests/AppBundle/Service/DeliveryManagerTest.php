<?php

namespace AppBundle\Service;

use AppBundle\BaseTest;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Service\DeliveryManager;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityRepository;
use Prophecy\Argument;

class DeliveryManagerTest extends BaseTest
{
    private $taxRateResolver;
    private $calculator;
    private $taxCategoryRepository;

    public function setUp()
    {
        parent::setUp();

        $this->taxRateResolver = static::$kernel->getContainer()->get('sylius.tax_rate_resolver');
        $this->calculator = static::$kernel->getContainer()->get('sylius.tax_calculator');
        $this->taxCategoryRepository = static::$kernel->getContainer()->get('sylius.repository.tax_category');
    }

    public function testGetPrice()
    {
        $pricingRuleRepository = $this->prophesize(EntityRepository::class);

        $rule1 = new PricingRule();
        $rule1->setExpression('distance in 0..3000');
        $rule1->setPrice(5.99);

        $rule2 = new PricingRule();
        $rule2->setExpression('distance in 3000..5000');
        $rule2->setPrice(6.99);

        $rule3 = new PricingRule();
        $rule3->setExpression('distance in 5000..7500');
        $rule3->setPrice(8.99);

        $rules = new ArrayCollection([
            $rule1,
            $rule2,
            $rule3,
        ]);

        $pricingRuleRepository
            ->findBy(Argument::type('array'), Argument::type('array'))
            ->willReturn($rules);

        $deliveryManager = new DeliveryManager(
            $pricingRuleRepository->reveal(),
            $this->taxRateResolver,
            $this->calculator,
            $this->taxCategoryRepository,
            'tva_livraison'
        );

        $delivery = new Delivery();
        $delivery->setDistance(1500);

        $this->assertEquals(5.99, $deliveryManager->getPrice($delivery));
    }

    public function testApplyTaxes()
    {
        $this->createTaxCategory('TVA livraison', 'tva_livraison', 'TVA 20%', 'tva_20', 0.20, 'float');

        $pricingRuleRepository = $this->prophesize(EntityRepository::class);

        $deliveryManager = new DeliveryManager(
            $pricingRuleRepository->reveal(),
            $this->taxRateResolver,
            $this->calculator,
            $this->taxCategoryRepository,
            'tva_livraison'
        );

        // 3.5 - (3.5 / (1 + 0.20)) = 0.58
        $delivery = new Delivery();
        $delivery->setPrice(3.50);

        $deliveryManager->applyTaxes($delivery);

        $this->assertEquals(02.92, $delivery->getTotalExcludingTax());
        $this->assertEquals(00.58, $delivery->getTotalTax());
        $this->assertEquals(03.50, $delivery->getTotalIncludingTax());
    }
}
