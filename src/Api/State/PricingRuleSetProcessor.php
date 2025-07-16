<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Service\PricingRuleSetManager;

class PricingRuleSetProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ProcessorInterface $decorated,
        private readonly PricingRuleSetManager $pricingRuleSetManager,

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
                $this->pricingRuleSetManager->setPricingRuleName($rule, $name);
            }
        }
    }
}
