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
    {
    }

    public function visit(Delivery $delivery): void
    {
        $this->price = null;
        $this->matchedRules = [];

        if ($this->ruleSet->getStrategy() === 'find') {
            foreach ($this->ruleSet->getRules() as $rule) {
                $result = $this->apply($rule, $delivery);
                if ($result['matched']) {
                    $this->logger->info(sprintf('Matched rule "%s", price: %d', $rule->getExpression(), $result['price']), [
                            'strategy' => $this->ruleSet->getStrategy(),
                            'target' => $rule->getTarget(),
                        ]
                    );
                    $this->matchedRules[] = $rule;
                    $this->price = $result['price'];
                    break;
                }
            }
        }

        if ($this->ruleSet->getStrategy() === 'map') {
            foreach ($this->ruleSet->getRules() as $rule) {
                $result = $this->apply($rule, $delivery);
                if ($result['matched']) {
                    $this->logger->info(sprintf('Matched rule "%s", adding %d to price', $rule->getExpression(), $result['price']), [
                        'strategy' => $this->ruleSet->getStrategy(),
                        'target' => $rule->getTarget(),
                    ]);

                    $this->matchedRules[] = $rule;
                    $this->price += $result['price'];
                }
            }
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

    private function apply(PricingRule $rule, Delivery $delivery)
    {
        if ($rule->getTarget() === PricingRule::TARGET_DELIVERY) {
            return $this->visitDelivery($rule, $delivery);
        }

        if ($rule->getTarget() === PricingRule::TARGET_TASK) {
            return $this->visitTasks($rule, $delivery->getTasks());
        }

        // LEGACY_TARGET_DYNAMIC is used for backward compatibility
        // for more info see PricingRule::LEGACY_TARGET_DYNAMIC
        if ($rule->getTarget() === PricingRule::LEGACY_TARGET_DYNAMIC) {
            $tasks = $delivery->getTasks();

            if (count($tasks) > 2) {
                return $this->visitTasks($rule, $delivery->getTasks());
            } else {
                return $this->visitDelivery($rule, $delivery);
            }
        }

        $this->logger->warning(sprintf('Unknown target "%s"', $rule->getTarget()));

        return [
            'matched' => false,
            'price' => null,
        ];
    }

    private function visitDelivery(PricingRule $rule, Delivery $delivery)
    {
        $matched = false;
        $price = null;

        $deliveryAsExpressionLanguageValues = Delivery::toExpressionLanguageValues($delivery);

        if ($rule->matches($deliveryAsExpressionLanguageValues, $this->expressionLanguage)) {
            $matched = true;
            $price = $rule->evaluatePrice($deliveryAsExpressionLanguageValues, $this->expressionLanguage);
        }

        return [
            'matched' => $matched,
            'price' => $price,
        ];
    }

    private function visitTasks(PricingRule $rule, array $tasks)
    {
        $matched = false;
        $price = null;

        foreach ($tasks as $task) {
            $result = $this->visitTask($rule, $task);

            if (!$matched && $result['matched']) {
                $matched = true;
            }

            if ($result['price'] !== null) {
                $price += $result['price'];
            }
        }

        return [
            'matched' => $matched,
            'price' => $price,
        ];
    }

    private function visitTask(PricingRule $rule, Task $task)
    {
        $matched = false;
        $price = null;

        $taskAsExpressionLanguageValues = $task->toExpressionLanguageValues();

        if ($rule->matches($taskAsExpressionLanguageValues, $this->expressionLanguage)) {
            $matched = true;
            $price = $rule->evaluatePrice($taskAsExpressionLanguageValues, $this->expressionLanguage);
        }

        return [
            'matched' => $matched,
            'price' => $price,
        ];
    }
}
