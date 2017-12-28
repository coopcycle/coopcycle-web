<?php

namespace AppBundle\Service;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\ApiUser;
use AppBundle\Exception\InvalidStatusException;
use AppBundle\ExpressionLanguage\ZoneExpressionLanguageProvider;
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
    private $zoneExpressionLanguageProvider;

    public function __construct(EntityRepository $pricingRuleRepository,
        TaxRateResolverInterface $taxRateResolver, CalculatorInterface $calculator,
        TaxCategoryRepositoryInterface $taxCategoryRepository, $taxCategoryCode, ZoneExpressionLanguageProvider $zoneExpressionLanguageProvider)
    {
        $this->pricingRuleRepository = $pricingRuleRepository;
        $this->taxRateResolver = $taxRateResolver;
        $this->calculator = $calculator;
        $this->taxCategoryRepository = $taxCategoryRepository;
        $this->taxCategoryCode = $taxCategoryCode;
        $this->zoneExpressionLanguageProvider = $zoneExpressionLanguageProvider;
    }

    public function dispatch(Delivery $delivery, ApiUser $user)
    {
        if (!$user->hasRole('ROLE_COURIER')) {
            $message = sprintf('Delivery #%d cannot be dispatched to user %s', $delivery->getId(), $user->getUsername());
            throw new AccessDeniedException($message);
        }

        // Delivery MUST have status = WAITING
        if ($delivery->getStatus() !== Delivery::STATUS_WAITING) {
            $message = sprintf('Delivery #%d cannot be accepted anymore', $delivery->getId());
            throw new InvalidStatusException($message);
        }

        $delivery->setCourier($user);
        $delivery->setStatus(Delivery::STATUS_DISPATCHED);
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
        $language = new ExpressionLanguage();
        $language->registerProvider($this->zoneExpressionLanguageProvider);

        foreach ($ruleSet->getRules() as $rule) {
            if ($rule->matches($delivery, $language)) {
                return $rule->getPrice();
            }
        }
    }
}
