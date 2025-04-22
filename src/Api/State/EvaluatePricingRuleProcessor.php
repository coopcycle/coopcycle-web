<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\YesNoOutput;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Pricing\PriceCalculationVisitor;
use AppBundle\ExpressionLanguage\ExpressionLanguage;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class EvaluatePricingRuleProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly DeliveryProcessor $decorated,
        private readonly ItemProvider $provider,
        private readonly ExpressionLanguage $expressionLanguage,
        private readonly LoggerInterface $logger = new NullLogger())
    {}

    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $delivery = $this->decorated->process($data, $operation, $uriVariables, $context);
        /** @var PricingRule */
        $pricingRule = $this->provider->provide($operation, $uriVariables, $context);

        $pricingRuleSet = $pricingRule->getRuleSet();

        // clone the pricing rule set with only one rule that we want to evaluate
        $ruleSet = clone $pricingRuleSet;
        $ruleSet->setRules([$pricingRule]);

        $visitor = new PriceCalculationVisitor($ruleSet, $this->expressionLanguage, $this->logger);
        $visitor->visit($delivery);

        $output = new YesNoOutput();
        $output->result = count($visitor->getMatchedRules()) > 0;

        return $output;
    }
}
