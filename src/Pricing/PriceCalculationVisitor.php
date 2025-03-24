<?php

namespace AppBundle\Pricing;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\Task;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class PriceCalculationVisitor
{
    private ?int $price = null;
    private array $matchedRules = [];

    public function __construct(
        private PricingRuleSet $ruleSet,
        private ExpressionLanguage $expressionLanguage,
        private LoggerInterface $logger = new NullLogger()
    )
    {
    }

    public function visit(Delivery $delivery): void
    {
        $this->price = null;
        $this->matchedRules = [];

        if ($this->ruleSet->getStrategy() === 'map') {
            $tasks = $delivery->getTasks();

            // Apply the rules to each task/point
            foreach ($tasks as $task) {
                $result = $this->visitTask($this->ruleSet, $delivery, $task);

                $this->matchedRules = array_merge($this->matchedRules, $result['matchedRules']);
                $this->price += $result['price'];
            }
        }

        // Apply the rules to the whole delivery/order
        $result = $this->visitDelivery($this->ruleSet, $delivery);

        if (array_count_values($result['matchedRules']) > 0) {
            $this->matchedRules = array_merge($this->matchedRules, $result['matchedRules']);
            $this->price += $result['price'];
        }

        if (count($this->matchedRules) === 0) {
            $this->logger->info(sprintf('No rule matched'), [
                'strategy' => $this->ruleSet->getStrategy(),
            ]);
        }
    }

    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function getMatchedRules(): array
    {
        return $this->matchedRules;
    }


    private function visitDelivery(PricingRuleSet $ruleSet, Delivery $delivery)
    {
        $matchedRules = [];
        $pricePerDelivery = null;

        $deliveryAsExpressionLanguageValues = Delivery::toExpressionLanguageValues($delivery);

        if ($ruleSet->getStrategy() === 'find') {
            foreach ($ruleSet->getRules() as $rule) {
                // LEGACY_TARGET_DYNAMIC is used for backward compatibility
                // for more info see PricingRule::LEGACY_TARGET_DYNAMIC
                if ($rule->getTarget() === PricingRule::TARGET_DELIVERY || $rule->getTarget() === PricingRule::LEGACY_TARGET_DYNAMIC) {
                    if ($rule->matches($deliveryAsExpressionLanguageValues, $this->expressionLanguage)) {
                        $price = $rule->evaluatePrice($deliveryAsExpressionLanguageValues, $this->expressionLanguage);

                        $this->logger->info(sprintf('Matched rule "%s", price: %d', $rule->getExpression(), $price), [
                            'strategy' => $ruleSet->getStrategy(),
                            'target' => $rule->getTarget(),
                        ]);

                        $matchedRules[] = $rule;
                        $pricePerDelivery = $price;

                        return [
                            'matchedRules' => $matchedRules,
                            'price' => $pricePerDelivery,
                        ];
                    }
                }
            }
        }

        if ($ruleSet->getStrategy() === 'map') {
            $tasks = $delivery->getTasks();

            foreach ($ruleSet->getRules() as $rule) {
                // LEGACY_TARGET_DYNAMIC is used for backward compatibility
                // for more info see PricingRule::LEGACY_TARGET_DYNAMIC
                if ($rule->getTarget() === PricingRule::TARGET_DELIVERY || (count($tasks) <= 2 && $rule->getTarget() === PricingRule::LEGACY_TARGET_DYNAMIC)) {
                    if ($rule->matches($deliveryAsExpressionLanguageValues, $this->expressionLanguage)) {
                        $price = $rule->evaluatePrice($deliveryAsExpressionLanguageValues, $this->expressionLanguage);

                        $this->logger->info(sprintf('Matched rule "%s", adding %d to price', $rule->getExpression(), $price), [
                            'strategy' => $ruleSet->getStrategy(),
                            'target' => $rule->getTarget(),
                        ]);

                        $matchedRules[] = $rule;
                        $pricePerDelivery += $price;
                    }
                }
            }

            return [
                'matchedRules' => $matchedRules,
                'price' => $pricePerDelivery,
            ];
        }

        return [
            'matchedRules' => $matchedRules,
            'price' => $pricePerDelivery,
        ];
    }

    private function visitTask(PricingRuleSet $ruleSet, Delivery $delivery, Task $task)
    {
        $tasks = $delivery->getTasks();

        $matchedRules = [];
        $pricePerTask = null;

        if ($ruleSet->getStrategy() === 'map') {
            $taskAsExpressionLanguageValues = $task->toExpressionLanguageValues();

            foreach ($ruleSet->getRules() as $rule) {
                // LEGACY_TARGET_DYNAMIC is used for backward compatibility
                // for more info see PricingRule::LEGACY_TARGET_DYNAMIC
                if ($rule->getTarget() === PricingRule::TARGET_TASK || (count($tasks) > 2 && $rule->getTarget() === PricingRule::LEGACY_TARGET_DYNAMIC)) {
                    if ($rule->matches($taskAsExpressionLanguageValues, $this->expressionLanguage)) {
                        $price = $rule->evaluatePrice($taskAsExpressionLanguageValues, $this->expressionLanguage);

                        $this->logger->info(sprintf('Matched rule "%s", adding %d to price', $rule->getExpression(), $price), [
                            'strategy' => $ruleSet->getStrategy(),
                            'target' => $rule->getTarget(),
                        ]);

                        $matchedRules[] = $rule;
                        $pricePerTask += $price;
                    }
                }
            }
        }

        return [
            'matchedRules' => $matchedRules,
            'price' => $pricePerTask,
        ];
    }
}
