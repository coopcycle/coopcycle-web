<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Entity\Delivery\PricingMatrix;
use AppBundle\Pricing\Matrix\PricingMatrixRuleGenerator;

/**
 * Deleting a matrix deletes the rules it generated, and leaves the rest of the rule set
 * alone. The ProductOptionValues of those rules survive, their pricing_rule_id being set
 * to null, so the price breakdown of past orders stays readable.
 */
class PricingMatrixRemoveProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ProcessorInterface $decorated,
        private readonly PricingMatrixRuleGenerator $pricingMatrixRuleGenerator,
    ) {
    }

    public function process(
        $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = []
    ) {
        if ($data instanceof PricingMatrix) {
            $ruleSet = $data->getRuleSet();

            $this->pricingMatrixRuleGenerator->clear($data);

            $ruleSet?->removeMatrix($data);
        }

        return $this->decorated->process($data, $operation, $uriVariables, $context);
    }
}
