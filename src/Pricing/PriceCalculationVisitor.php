<?php

namespace AppBundle\Pricing;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\Delivery\ProductVariant;
use AppBundle\Entity\Task;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

// a simplified version of Sylius OrderProcessor structure
// migrate to Sylius later on?
class PriceCalculationVisitor
{
    private array $matchedRules = [];
    private array $productVariants = [];
    private ?int $price = null;

    public function __construct(
        private PricingRuleSet $ruleSet,
        private ExpressionLanguage $expressionLanguage,
        private LoggerInterface $logger = new NullLogger()
    )
    {
    }

    public function visit(Delivery $delivery): void
    {
        $this->matchedRules = [];
        $this->productVariants = [];
        $this->price = null;

        $tasks = $delivery->getTasks();

        // Apply the rules to each task/point
        foreach ($tasks as $task) {
            $result = $this->visitTask($this->ruleSet, $delivery, $task);
            $matchedRulesPerTask = $result['matchedRules'];
            if (count($matchedRulesPerTask) > 0) {
                $this->matchedRules = array_merge($this->matchedRules, $matchedRulesPerTask);
                $this->productVariants = array_merge($this->productVariants, $result['productVariants']);
            }
        }

        // Apply the rules to the whole delivery/order
        $result = $this->visitDelivery($this->ruleSet, $delivery);
        $matchedRulesPerDelivery = $result['matchedRules'];
        if (count($matchedRulesPerDelivery) > 0) {
            $this->matchedRules = array_merge($this->matchedRules, $matchedRulesPerDelivery);
            $this->productVariants = array_merge($this->productVariants, $result['productVariants']);
        }


        if (count($this->productVariants) === 0) {
            $this->logger->info(sprintf('No rule matched'), [
                'strategy' => $this->ruleSet->getStrategy(),
            ]);
        } else {
            $this->price = $this->process($this->productVariants);

            $this->logger->info(sprintf('Calculated price: %d', $this->price), [
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
        $productVariants = [];

        $deliveryAsExpressionLanguageValues = Delivery::toExpressionLanguageValues($delivery);

        if ($ruleSet->getStrategy() === 'find') {
            foreach ($ruleSet->getRules() as $rule) {
                // LEGACY_TARGET_DYNAMIC is used for backward compatibility
                // for more info see PricingRule::LEGACY_TARGET_DYNAMIC
                if ($rule->getTarget() === PricingRule::TARGET_DELIVERY || $rule->getTarget() === PricingRule::LEGACY_TARGET_DYNAMIC) {
                    if ($rule->matches($deliveryAsExpressionLanguageValues, $this->expressionLanguage)) {
                        $this->logger->info(sprintf('Matched rule "%s"', $rule->getExpression()), [
                            'strategy' => $ruleSet->getStrategy(),
                            'target' => $rule->getTarget(),
                        ]);

                        $matchedRules[] = $rule;
                        $productVariants[] = $rule->apply($deliveryAsExpressionLanguageValues, $this->expressionLanguage);

                        return [
                            'matchedRules' => $matchedRules,
                            'productVariants' => $productVariants,
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
                        $this->logger->info(sprintf('Matched rule "%s"', $rule->getExpression()), [
                            'strategy' => $ruleSet->getStrategy(),
                            'target' => $rule->getTarget(),
                        ]);

                        $matchedRules[] = $rule;
                        $productVariants[] = $rule->apply($deliveryAsExpressionLanguageValues, $this->expressionLanguage);
                    }
                }
            }

            return [
                'matchedRules' => $matchedRules,
                'productVariants' => $productVariants,
            ];
        }

        return [
            'matchedRules' => $matchedRules,
            'productVariants' => $productVariants,
        ];
    }

    private function visitTask(PricingRuleSet $ruleSet, Delivery $delivery, Task $task)
    {
        $tasks = $delivery->getTasks();

        $matchedRules = [];
        $productVariants = [];

        $taskAsExpressionLanguageValues = $task->toExpressionLanguageValues();

        if ($ruleSet->getStrategy() === 'find') {
            foreach ($ruleSet->getRules() as $rule) {
                if ($rule->getTarget() === PricingRule::TARGET_TASK) {
                    if ($rule->matches($taskAsExpressionLanguageValues, $this->expressionLanguage)) {
                        $price = $rule->evaluatePrice($taskAsExpressionLanguageValues, $this->expressionLanguage);

                        $this->logger->info(sprintf('Matched rule "%s", price: %d', $rule->getExpression(), $price), [
                            'strategy' => $ruleSet->getStrategy(),
                            'target' => $rule->getTarget(),
                        ]);

                        $matchedRules[] = $rule;
                        $pricePerTask = $price;

                        return [
                            'matchedRules' => $matchedRules,
                            'price' => $pricePerTask,
                        ];
                    }
                }
            }
        }

        if ($ruleSet->getStrategy() === 'map') {
            foreach ($ruleSet->getRules() as $rule) {
                // LEGACY_TARGET_DYNAMIC is used for backward compatibility
                // for more info see PricingRule::LEGACY_TARGET_DYNAMIC
                if ($rule->getTarget() === PricingRule::TARGET_TASK || (count($tasks) > 2 && $rule->getTarget() === PricingRule::LEGACY_TARGET_DYNAMIC)) {
                    if ($rule->matches($taskAsExpressionLanguageValues, $this->expressionLanguage)) {
                        $this->logger->info(sprintf('Matched rule "%s"', $rule->getExpression()), [
                            'strategy' => $ruleSet->getStrategy(),
                            'target' => $rule->getTarget(),
                        ]);

                        $matchedRules[] = $rule;
                        $productVariants[] = $rule->apply($taskAsExpressionLanguageValues, $this->expressionLanguage);
                    }
                }
            }
        }

        return [
            'matchedRules' => $matchedRules,
            'productVariants' => $productVariants,
        ];
    }

    /**
     * @param ProductVariant[] $productVariants
     * @return int
     */
    private function process(array $productVariants): int
    {
        $totalPrice = 0;

        foreach ($productVariants as $productVariant) {
            $priceAdditive = $productVariant->getPriceAdditive();
            $priceMultiplier = $productVariant->getPriceMultiplier();

            $totalPrice += $priceAdditive;
            $totalPrice = (int) ceil($totalPrice * ($priceMultiplier / 100 / 100));
        }

        // Later on: group the same product variants into one OrderItem

        return $totalPrice;
    }
}
