<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\Sylius\ProductOption;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Sylius\Component\Locale\Provider\LocaleProviderInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

class PricingRuleSetProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ProcessorInterface $decorated,
        private readonly EntityManagerInterface $entityManager,
        private readonly FactoryInterface $productOptionFactory,
        private readonly LocaleProviderInterface $localeProvider,
    ) {
    }

    public function process(
        $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = []
    ) {
        // Handle ProductOption creation/update for each rule before processing
        if ($data instanceof PricingRuleSet) {
            $this->processRulesNames($data);
        }

        // Process the PricingRuleSet using the default processor
        $result = $this->decorated->process($data, $operation, $uriVariables, $context);

        return $result;
    }

    private function processRulesNames(PricingRuleSet $pricingRuleSet): void
    {
        $rules = $pricingRuleSet->getRules();

        // Process each rule to handle ProductOption names
        foreach ($rules as $rule) {
            $nameInput = $rule->getNameInput();

            if ($nameInput !== null && !empty(trim($nameInput))) {
                $name = trim($nameInput);
                $this->handleName($rule, $name);
            }
        }
    }

    private function handleName(PricingRule $pricingRule, string $name): void
    {
        $productOption = $pricingRule->getProductOption();

        if ($productOption === null) {
            // Create a new ProductOption
            $productOption = $this->createProductOption($name);
            $pricingRule->setProductOption($productOption);
        } else {
            // Update existing ProductOption name if different
            if ($productOption->getName() !== $name) {
                // Set current locale before setting the name for translatable entities
                $productOption->setCurrentLocale($this->localeProvider->getDefaultLocaleCode());
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
