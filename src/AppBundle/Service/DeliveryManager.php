<?php

namespace AppBundle\Service;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRuleSet;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Sylius\Component\Taxation\Calculator\CalculatorInterface;
use Sylius\Component\Taxation\Repository\TaxCategoryRepositoryInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;

class DeliveryManager
{
    private $pricingRuleRepository;
    private $taxRateResolver;
    private $calculator;
    private $taxCategoryRepository;
    private $taxCategoryCode;
    private $expressionLanguage;

    public function __construct(EntityRepository $pricingRuleRepository,
        TaxRateResolverInterface $taxRateResolver, CalculatorInterface $calculator,
        TaxCategoryRepositoryInterface $taxCategoryRepository, $taxCategoryCode, ExpressionLanguage $expressionLanguage)
    {
        $this->pricingRuleRepository = $pricingRuleRepository;
        $this->taxRateResolver = $taxRateResolver;
        $this->calculator = $calculator;
        $this->taxCategoryRepository = $taxCategoryRepository;
        $this->taxCategoryCode = $taxCategoryCode;
        $this->expressionLanguage = $expressionLanguage;
    }

    public function applyTaxes(Delivery $delivery)
    {
        $taxCategory = $this->taxCategoryRepository->findOneBy(['code' => $this->taxCategoryCode]);

        $delivery->setTaxCategory($taxCategory);

        $taxRate = $this->taxRateResolver->resolve($delivery);

        $totalIncludingTax = $delivery->getPrice();
        $totalTax = $this->calculator->calculate($totalIncludingTax, $taxRate);
        $totalExcludingTax = $totalIncludingTax - $totalTax;

        $delivery->setTotalExcludingTax($totalExcludingTax);
        $delivery->setTotalTax($totalTax);
        $delivery->setTotalIncludingTax($totalIncludingTax);
    }

    public function getPrice(Delivery $delivery, PricingRuleSet $ruleSet)
    {
        foreach ($ruleSet->getRules() as $rule) {
            if ($rule->matches($delivery, $this->expressionLanguage)) {
                return $rule->evaluatePrice($delivery, $this->expressionLanguage);
            }
        }
    }
}
