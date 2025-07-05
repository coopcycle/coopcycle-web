<?php

namespace AppBundle\Service;

use AppBundle\Entity\Contract;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\DeliveryForm;
use AppBundle\Entity\Store;
use AppBundle\Entity\Sylius\ProductOption;
use AppBundle\Entity\Sylius\ProductOptionValue;
use AppBundle\Sylius\Product\ProductOptionValueFactory;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Sylius\Component\Locale\Provider\LocaleProviderInterface;
use Sylius\Component\Product\Repository\ProductOptionRepositoryInterface;
use Sylius\Component\Product\Repository\ProductRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

class PricingRuleSetManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductOptionRepositoryInterface $productOptionRepository,
        private readonly FactoryInterface $productOptionFactory,
        private readonly ProductOptionValueFactory $productOptionValueFactory,
        private readonly LocaleProviderInterface $localeProvider,
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
            $productOption = $this->productOptionRepository->findPricingRuleProductOption(
                $this->entityManager,
                $this->productRepository,
                $this->productOptionFactory,
                $this->localeProvider
            );

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
