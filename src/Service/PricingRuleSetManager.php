<?php

namespace AppBundle\Service;

use AppBundle\Entity\Contract;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\DeliveryForm;
use AppBundle\Entity\Store;
use AppBundle\Entity\Sylius\ProductOption;
use AppBundle\Entity\Sylius\ProductOptionRepository;
use AppBundle\Sylius\Product\ProductOptionFactory;
use AppBundle\Entity\Sylius\ProductOptionValue;
use AppBundle\Sylius\Product\ProductOptionValueFactory;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Sylius\Component\Locale\Provider\LocaleProviderInterface;

class PricingRuleSetManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductOptionRepository $productOptionRepository,
        private readonly ProductOptionValueFactory $productOptionValueFactory,
        private readonly LocaleProviderInterface $localeProvider,
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
        $productOptionValue = $pricingRule->getProductOptionValue();

        if ($productOptionValue === null) {
            $productOption = $this->productOptionRepository->findPricingRuleProductOption();

            // Create a new ProductOptionValue for this pricing rule
            $productOptionValue = $this->createProductOptionValue($productOption, $name);
            $pricingRule->setProductOptionValue($productOptionValue);
        } else {
            // Update existing ProductOptionValue name if different
            if ($pricingRule->getName() !== $name) {
                $productOptionValue->setValue($name);
            }
        }
    }

    //TODO: FIX
    private function createProductOption(string $name): ProductOption
    {
        $productOption = $this->productOptionFactory->createForOnDemandDelivery($name);

        // Persist the new ProductOption
        $this->entityManager->persist($productOption);

        return $productOption;
    }

    /**
     * Create a ProductOptionValue for a given ProductOption and name
     */
    private function createProductOptionValue(
        ProductOption $productOption,
        string $name
    ): ProductOptionValue {
        /** @var ProductOptionValue $productOptionValue */
        $productOptionValue = $this->productOptionValueFactory->createNew();

        // Set current locale before setting the value for translatable entities
        $productOptionValue->setCurrentLocale($this->localeProvider->getDefaultLocaleCode());

        $productOptionValue->setCode(Uuid::uuid4()->toString());
        $productOptionValue->setValue($name);
        $productOptionValue->setOption($productOption);

        // Persist the new ProductOptionValue
        $this->entityManager->persist($productOptionValue);

        return $productOptionValue;
    }

}
