<?php

namespace AppBundle\Service;

use AppBundle\Entity\Contract;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\DeliveryForm;
use AppBundle\Entity\Store;
use AppBundle\Entity\Sylius\ProductOption;
use AppBundle\Sylius\Product\ProductOptionFactory;
use Doctrine\ORM\EntityManagerInterface;

class PricingRuleSetManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductOptionFactory $productOptionFactory,
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
        $productOption = $pricingRule->getProductOption();

        if ($productOption === null) {
            // Create a new ProductOption
            $productOption = $this->createProductOption($name);
            $pricingRule->setProductOption($productOption);
        } else {
            // Update existing ProductOption name if different
            if ($pricingRule->getName() !== $name) {
                $productOption->setName($name);
            }
        }
    }

    private function createProductOption(string $name): ProductOption
    {
        $productOption = $this->productOptionFactory->createForOnDemandDelivery($name);

        // Persist the new ProductOption
        $this->entityManager->persist($productOption);

        return $productOption;
    }

}
