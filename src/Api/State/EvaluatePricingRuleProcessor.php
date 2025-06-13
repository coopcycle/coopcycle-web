<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\DeliveryDto;
use AppBundle\Api\Dto\YesNoOutput;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Pricing\PriceCalculationVisitor;

final class EvaluatePricingRuleProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly DeliveryProcessor $decorated,
        private readonly ItemProvider $provider,
        private readonly PriceCalculationVisitor $priceCalculationVisitor)
    {}

    /**
     * @param DeliveryDto $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $delivery = $this->decorated->process($data, $operation, $uriVariables, $context);
        /** @var PricingRule */
        $pricingRule = $this->provider->provide($operation, $uriVariables, $context);

        $pricingRuleSet = $pricingRule->getRuleSet();

        // clone the pricing rule set with only one rule that we want to evaluate
        $ruleSet = clone $pricingRuleSet;
        $ruleSet->setRules([$pricingRule]);

        $result = $this->priceCalculationVisitor->visit($delivery, $ruleSet);

        $output = new YesNoOutput();
        $output->result = count($result->matchedRules) > 0;

        return $output;
    }
}
