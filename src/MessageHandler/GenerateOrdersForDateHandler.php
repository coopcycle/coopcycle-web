<?php

declare(strict_types=1);

namespace AppBundle\MessageHandler;

use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Task;
use AppBundle\Exception\GenerateOrdersException;
use AppBundle\Message\GenerateOrdersForDate;
use AppBundle\Service\DeliveryCreatedNotifier;
use AppBundle\Service\DeliveryOrderManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Recurr\Transformer\ArrayTransformer;
use Recurr\Transformer\Constraint\BetweenConstraint;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class GenerateOrdersForDateHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DeliveryOrderManager $deliveryOrderManager,
        private readonly DeliveryCreatedNotifier $deliveryCreatedNotifier,
        private readonly LoggerInterface $logger,
    )
    {
    }

    public function __invoke(GenerateOrdersForDate $message): void
    {
        $date = $message->getDate();

        $this->entityManager->getFilters()->enable('soft_deleteable');
        try {
            /** @var Task\RecurrenceRule[] $allSubscriptions */
            $allSubscriptions = $this->entityManager->getRepository(Task\RecurrenceRule::class)->findByGenerateOrders(true);
        } finally {
            $this->entityManager->getFilters()->disable('soft_deleteable');
        }

        $subscriptions = array_filter($allSubscriptions, function (Task\RecurrenceRule $subscription) {
            return !$subscription->isPaused();
        });

        $subscriptions = array_filter($subscriptions, function (Task\RecurrenceRule $subscription) use ($date) {
            return $this->filterByDate($subscription, $date);
        });

        $subscriptions = array_filter($subscriptions, function (Task\RecurrenceRule $subscription) use ($date) {
            return $this->filterWithoutOrdersOnDate($subscription, $date);
        });

        if (empty($subscriptions)) {
            $this->logger->info(
                sprintf('Generated %d recurring order(s)', 0),
                ['date' => $date, 'recurrence_rules' => 0]
            );

            return;
        }

        $count = 0;

        // Send a single recap notification for all the deliveries created below,
        // instead of one notification per delivery
        $this->deliveryCreatedNotifier->startBatch();

        $failures = [];

        try {
            foreach ($subscriptions as $subscription) {
                try {
                    $order = $this->deliveryOrderManager->createOrderFromRecurrenceRule($subscription, $date);
                    if (!is_null($order)) {
                        $count++;
                    }
                } catch (\Throwable $e) {
                    // One bad rule should not block the other rules for the date.
                    // Failed rules are retried with the whole message, successes
                    // are skipped on retry via filterWithoutOrdersOnDate().
                    $failures[] = $subscription->getId();
                    $this->logger->error(
                        sprintf('Failed to generate recurring order: %s', $e->getMessage()),
                        ['date' => $date, 'recurrence_rule' => $subscription->getId()]
                    );
                }
            }

            $this->deliveryCreatedNotifier->endBatch();
        } catch (\Throwable $e) {
            // Unexpected failure (loading rules, dispatching recap, ...):
            // send nothing, so retries don't spam partial recaps.
            $this->deliveryCreatedNotifier->abortBatch();
            throw $e;
        }

        if (!empty($failures)) {
            throw new GenerateOrdersException(
                sprintf('Failed to generate recurring orders for %d rule(s): %s', count($failures), implode(', ', $failures))
            );
        }

        // There is no feedback channel to the dashboard yet, so this log line is
        // the only record that a generation ran and what it produced
        $this->logger->info(
            sprintf('Generated %d recurring order(s)', $count),
            ['date' => $date, 'recurrence_rules' => count($subscriptions)]
        );
    }

    private function filterByDate(Task\RecurrenceRule $recurrence, string $startDate): bool
    {
        $after = new \DateTime($startDate . ' 00:00');
        $before = new \DateTime($startDate . ' 23:59');

        $transformer = new ArrayTransformer();
        $constraint = new BetweenConstraint(
            $after,
            $before,
            $inc = true
        );

        $rule = $recurrence->getRule();

        $rule->setStartDate($recurrence->getCreatedAt());
        $rule->setEndDate(null);

        $occurrences = $transformer->transform($rule, $constraint);

        return count($occurrences) > 0;
    }

    private function filterWithoutOrdersOnDate(Task\RecurrenceRule $subscription, string $startDate): bool
    {
        $date = new \DateTime($startDate . ' 00:00');

        /** @var Order[] $orders */
        $orders = $this->entityManager->getRepository(Order::class)->findBySubscriptionAndDate($subscription, $date);

        return empty($orders);
    }
}
