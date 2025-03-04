<?php

namespace AppBundle\Pricing;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRule;
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

    /**
     * @return int|null
     */
    public function getPrice(Delivery $delivery): ?int
    {
        $this->visitDelivery($delivery);
        return $this->price;
    }

    private function visitDelivery(Delivery $delivery): void
    {
        $deliveryAsExpressionLanguageValues = Delivery::toExpressionLanguageValues($delivery);

        if ($this->ruleSet->getStrategy() === 'find') {

            foreach ($this->ruleSet->getRules() as $rule) {
                if ($rule->matches($deliveryAsExpressionLanguageValues, $this->expressionLanguage)) {
                    $this->logger->info(sprintf('Matched rule "%s"', $rule->getExpression()), [
                            'strategy' => $this->ruleSet->getStrategy(),
                        ]
                    );
                    $this->matchedRules[] = $rule;
                    $this->price = $rule->evaluatePrice($deliveryAsExpressionLanguageValues, $this->expressionLanguage);
                    break;
                }
            }

            if (count($this->matchedRules) === 0) {
                $this->logger->info(sprintf('No rule matched'), [
                    'strategy' => $this->ruleSet->getStrategy(),
                ]);
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
                    if ($rule->matches($deliveryAsExpressionLanguageValues, $this->expressionLanguage)) {

                        $price = $rule->evaluatePrice($deliveryAsExpressionLanguageValues, $this->expressionLanguage);
                        $this->matchedRules[] = $rule;
                        $this->price += $price;

                        $this->logger->info(sprintf('Matched rule "%s", adding %d to price', $rule->getExpression(), $price), [
                            'strategy' => $this->ruleSet->getStrategy(),
                            'object' => 'delivery',
                        ]);
                    }
                }
            }
        }

        if (count($this->matchedRules) === 0) {
            $this->logger->info(sprintf('No rule matched'), [
                'strategy' => $this->ruleSet->getStrategy(),
            ]);
        }
    }

    private function visitTask(Task $task): void
    {
        $taskAsExpressionLanguageValues = $task->toExpressionLanguageValues();

        foreach ($this->ruleSet->getRules() as $rule) {
            if ($rule->matches($taskAsExpressionLanguageValues, $this->expressionLanguage)) {

                $price = $rule->evaluatePrice($taskAsExpressionLanguageValues, $this->expressionLanguage);
                $this->matchedRules[] = $rule;
                $this->price += $price;

                $this->logger->info(sprintf('Matched rule "%s", adding %d to price', $rule->getExpression(), $price), [
                    'strategy' => $this->ruleSet->getStrategy(),
                    'object' => 'task',
                ]);
            }
        }
    }
}
