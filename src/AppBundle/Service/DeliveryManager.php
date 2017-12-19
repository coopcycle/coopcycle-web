<?php

namespace AppBundle\Service;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\ApiUser;
use AppBundle\Exception\InvalidStatusException;
use Doctrine\ORM\EntityRepository;
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

    public function __construct(EntityRepository $pricingRuleRepository,
        TaxRateResolverInterface $taxRateResolver, CalculatorInterface $calculator,
        TaxCategoryRepositoryInterface $taxCategoryRepository, $taxCategoryCode)
    {
        $this->pricingRuleRepository = $pricingRuleRepository;
        $this->taxRateResolver = $taxRateResolver;
        $this->calculator = $calculator;
        $this->taxCategoryRepository = $taxCategoryRepository;
        $this->taxCategoryCode = $taxCategoryCode;
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

    public function getPrice(Delivery $delivery)
    {
        $rules = $this->pricingRuleRepository->findBy([], ['position' => 'ASC']);

        foreach ($rules as $rule) {
            if ($rule->matches($delivery)) {
                return $rule->getPrice();
            }
        }
    }
}
