<?php

namespace AppBundle\Service;

use AppBundle\Entity\Contract;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\DeliveryForm;
use AppBundle\Entity\Store;
use AppBundle\Entity\Sylius\ProductOption;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Sylius\Component\Locale\Provider\LocaleProviderInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;


class PricingRuleSetManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FactoryInterface $productOptionFactory,
        private readonly LocaleProviderInterface $localeProvider,
    )
    {}

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

    function setPricingRuleName(PricingRule $pricingRule, string $name): void
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
        /** @var ProductOption $productOption */
        $productOption = $this->productOptionFactory->createNew();

        // Set current locale before setting the name for translatable entities
        $productOption->setCurrentLocale($this->localeProvider->getDefaultLocaleCode());

        // Set basic properties
        $productOption->setCode(Uuid::uuid4()->toString());
        $productOption->setName($name);

        // Set default strategy and additional flag
        $productOption->setStrategy('free');
        $productOption->setAdditional(false);

        // Persist the new ProductOption
        $this->entityManager->persist($productOption);

        return $productOption;
    }
}
