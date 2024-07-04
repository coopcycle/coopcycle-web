<?php

namespace AppBundle\Pricing;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\Task;
use Psr\Log\LoggerInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class PriceCalculationVisitor
{
    private ?int $price = null;
    private array $matchedRules = [];

    public function __construct(
        private PricingRuleSet $ruleSet,
        private ExpressionLanguage $expressionLanguage,
        private LoggerInterface $logger)
    {}

    public function visitDelivery(Delivery $delivery): void
    {
        if ($this->ruleSet->getStrategy() === 'find') {

            foreach ($this->ruleSet->getRules() as $rule) {
                if ($rule->matches($delivery, $this->expressionLanguage)) {
                    $this->logger->info(sprintf('Matched rule "%s"', $rule->getExpression()));
                    $this->matchedRules[] = $rule;
                    $this->price = $rule->evaluatePrice($delivery, $this->expressionLanguage);
                    break;
                }
            }

            if (count($this->matchedRules) === 0) {
                $this->logger->info(sprintf('No rule matched, strategy: "%s"', $this->ruleSet->getStrategy()));
            }

            return;
        }

        if ($this->ruleSet->getStrategy() === 'map') {
            if (count($delivery->getTasks()) > 2 || $this->ruleSet->hasOption(PricingRuleSet::OPTION_MAP_ALL_TASKS)) {
                foreach ($delivery->getTasks() as $task) {
                    $this->visitTask($task);
                }
            } else {
                foreach ($this->ruleSet->getRules() as $rule) {
                    if ($rule->matches($delivery, $this->expressionLanguage)) {

                        $price = $rule->evaluatePrice($delivery, $this->expressionLanguage);
                        $this->matchedRules[] = $rule;
                        $this->price += $price;

                        $this->logger->info(sprintf('Matched rule "%s", adding %d to price', $rule->getExpression(), $price));
                    }
                }
            }
        }

        if (count($this->matchedRules) === 0) {
            $this->logger->info(sprintf('No rule matched, strategy: "%s"', $this->ruleSet->getStrategy()));
        }
    }

    public function visitTask(Task $task): void
    {
        foreach ($this->ruleSet->getRules() as $rule) {
            if ($task->matchesPricingRule($rule, $this->expressionLanguage)) {

                $price = $task->evaluatePrice($rule, $this->expressionLanguage);
                $this->matchedRules[] = $rule;
                $this->price += $price;

                $this->logger->info(sprintf('Matched rule "%s", adding %d to price', $rule->getExpression(), $price));
            }
        }
    }

    /**
     * @return int|null
     */
    public function getPrice(): ?int
    {
        return $this->price;
    }
}
