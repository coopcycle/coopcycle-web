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
// migration to Sylius later on
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

        if ($this->ruleSet->getStrategy() === 'find') {
            foreach ($this->ruleSet->getRules() as $rule) {
                $result = $this->apply($rule, $delivery);
                if ($result['matched']) {
                    $this->matchedRules[] = $rule;
                    $this->productVariants = array_merge($this->productVariants, $result['productVariants']);

                    $this->logger->info(sprintf('Matched rule "%s"', $rule->getExpression()), [
                            'strategy' => $this->ruleSet->getStrategy(),
                            'target' => $rule->getTarget(),
                        ]
                    );
                    break;
                }
            }
        }

        if ($this->ruleSet->getStrategy() === 'map') {
            foreach ($this->ruleSet->getRules() as $rule) {
                $result = $this->apply($rule, $delivery);
                if ($result['matched']) {
                    $this->matchedRules[] = $rule;
                    $this->productVariants = array_merge($this->productVariants, $result['productVariants']);

                    $this->logger->info(sprintf('Matched rule "%s"', $rule->getExpression()), [
                        'strategy' => $this->ruleSet->getStrategy(),
                        'target' => $rule->getTarget(),
                    ]);
                }
            }
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
            'productVariants' => [],
        ];
    }

    private function visitDelivery(PricingRule $rule, Delivery $delivery)
    {
        $matched = false;
        $productVariants = [];

        $deliveryAsExpressionLanguageValues = Delivery::toExpressionLanguageValues($delivery);

        if ($rule->matches($deliveryAsExpressionLanguageValues, $this->expressionLanguage)) {
            $matched = true;
            $productVariants[] = $rule->apply($deliveryAsExpressionLanguageValues, $this->expressionLanguage);
        }

        return [
            'matched' => $matched,
            'productVariants' => $productVariants,
        ];
    }

    private function visitTasks(PricingRule $rule, array $tasks)
    {
        $matched = false;
        $productVariants = [];

        foreach ($tasks as $task) {
            $result = $this->visitTask($rule, $task);

            if (!$matched && $result['matched']) {
                $matched = true;
            }

            if ($result['matched']) {
                $productVariants = array_merge($productVariants, $result['productVariants']);
            }
        }

        return [
            'matched' => $matched,
            'productVariants' => $productVariants,
        ];
    }

    private function visitTask(PricingRule $rule, Task $task)
    {
        $matched = false;
        $productVariants = [];

        $taskAsExpressionLanguageValues = $task->toExpressionLanguageValues();

        if ($rule->matches($taskAsExpressionLanguageValues, $this->expressionLanguage)) {
            $matched = true;
            $productVariants[] = $rule->apply($taskAsExpressionLanguageValues, $this->expressionLanguage);
        }

        return [
            'matched' => $matched,
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
            $totalPrice *= $priceMultiplier;
        }

        // Later on: group the same product variants into one OrderItem

        return $totalPrice;
    }
}
