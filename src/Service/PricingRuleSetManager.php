<?php

namespace AppBundle\Service;

use AppBundle\Entity\Contract;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\DeliveryForm;
use AppBundle\Entity\Store;
use AppBundle\Sylius\Product\ProductOptionValueFactory;
use Doctrine\ORM\EntityManagerInterface;

class PricingRuleSetManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductOptionValueFactory $productOptionValueFactory,
    ) {
    }

    /**
     * @return mixed[]
     */
    public function getPricingRuleSetApplications(PricingRuleSet $pricingRuleSet) {
        /*
            Returns an array of entities related to the pricingRuleSet.
        */

        $contracts = [];
        foreach ($this->getContracts($pricingRuleSet) as $contract) {
            if (null !== $contract->getContractor()) {
                $contracts[] = $contract;
            }
        }

        $results = array_merge(
            $this->getStores($pricingRuleSet),
            $contracts,
            $this->getDeliveryForms($pricingRuleSet)
        );

        return $results;
    }

    /**
     * @return Contract[]
     */
    public function getContracts(PricingRuleSet $pricingRuleSet) {
        $repository = $this->entityManager->getRepository(Contract::class);
        $query = $repository->createQueryBuilder('c')
            ->andWhere('c.variableDeliveryPrice = :pricingRuleSet')
            ->orWhere('c.variableCustomerAmount = :pricingRuleSet')
            ->setParameter('pricingRuleSet', $pricingRuleSet)
            ->getQuery();
        return $query->getResult();
    }

    /**
     * @return Store[]
     */
    public function getStores(PricingRuleSet $pricingRuleSet) {
        return $this->entityManager->getRepository(Store::class)->findBy(['pricingRuleSet' => $pricingRuleSet]);
    }

    /**
     * @return DeliveryForm[]]
     */
    public function getDeliveryForms(PricingRuleSet $pricingRuleSet) {
        return $this->entityManager->getRepository(DeliveryForm::class)->findBy(['pricingRuleSet' => $pricingRuleSet]);
    }

    public function setPricingRuleName(PricingRule $pricingRule, string $name): void
    {
        $productOptionValue = $pricingRule->getProductOptionValue();

        if (null !== $productOptionValue && $name === $productOptionValue->getValue()) {
            return;
        }

        // Do not modify existing ProductOptionValue, create a new one for each change
        $productOptionValue = $this->productOptionValueFactory->createForPricingRule($pricingRule, $name);
        $pricingRule->setProductOptionValue($productOptionValue);

        $this->entityManager->persist($productOptionValue);
    }
}
