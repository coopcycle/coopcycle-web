<?php

namespace AppBundle\Service;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\DeliveryOrder;
use AppBundle\Entity\Delivery\PricingRuleSet;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Taxation\Calculator\CalculatorInterface;
use Sylius\Component\Taxation\Repository\TaxCategoryRepositoryInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;

class DeliveryManager
{
    private $doctrine;
    private $taxRateResolver;
    private $calculator;
    private $taxCategoryRepository;
    private $taxCategoryCode;
    private $expressionLanguage;
    private $notificationManager;

    public function __construct(ManagerRegistry $doctrine,
        TaxRateResolverInterface $taxRateResolver, CalculatorInterface $calculator,
        TaxCategoryRepositoryInterface $taxCategoryRepository, $taxCategoryCode, ExpressionLanguage $expressionLanguage,
        NotificationManager $notificationManager)
    {
        $this->doctrine = $doctrine;
        $this->taxRateResolver = $taxRateResolver;
        $this->calculator = $calculator;
        $this->taxCategoryRepository = $taxCategoryRepository;
        $this->taxCategoryCode = $taxCategoryCode;
        $this->expressionLanguage = $expressionLanguage;
        $this->notificationManager = $notificationManager;
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

    public function confirmOrder(OrderInterface $order)
    {
        $deliveryOrder = $this->doctrine
            ->getRepository(DeliveryOrder::class)
            ->findOneByOrder($order);

        $this->notificationManager->notifyDeliveryConfirmed($order, $deliveryOrder->getUser()->getEmail());
    }
}
