<?php

declare(strict_types=1);

namespace AppBundle\MessageHandler;

use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Task;
use AppBundle\Entity\Task\RecurrenceRuleGeneration;
use AppBundle\Entity\Task\RecurrenceRuleGenerationRepository;
use AppBundle\Exception\GenerateOrdersException;
use AppBundle\Message\GenerateOrdersForDate;
use AppBundle\Messenger\TransactionalMessages;
use AppBundle\Service\DeliveryCreatedNotifier;
use AppBundle\Service\DeliveryOrderManager;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Recurr\Transformer\ArrayTransformer;
use Recurr\Transformer\Constraint\BetweenConstraint;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class GenerateOrdersForDateHandler
{
    /**
     * The first run plus Messenger's 3 retries.
     */
    private const MAX_ATTEMPTS = 4;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RecurrenceRuleGenerationRepository $generationRepository,
        private readonly DeliveryOrderManager $deliveryOrderManager,
        private readonly DeliveryCreatedNotifier $deliveryCreatedNotifier,
        private readonly LoggerInterface $logger,
        private readonly TransactionalMessages $transactionalMessages,
        private readonly ManagerRegistry $doctrine,
    )
    {
    }

    public function __invoke(GenerateOrdersForDate $message): void
    {
        $date = $message->getDate();

        $generation = $this->loadGeneration($date);

        // Messenger only counts retries for exceptions. A worker that dies (out
        // of memory, killed) never acknowledges the message, which is delivered
        // again and again, and each delivery can create orders. A run still
        // "started" when picked up again is one that died, so stop there.
        if (RecurrenceRuleGeneration::STATUS_STARTED === $generation->getStatus()
            && $generation->getAttempts() >= self::MAX_ATTEMPTS) {
            $generation->abort(sprintf('The worker crashed %d times in a row', $generation->getAttempts()));
            $this->entityManager->flush();

            $this->logger->error(
                sprintf('Gave up generating recurring orders after %d attempts', $generation->getAttempts()),
                ['date' => $date]
            );

            return;
        }

        $generation->start();
        $this->entityManager->flush();

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
            $generation->finish();
            $this->entityManager->flush();

            $this->logger->info(
                sprintf('Generated %d recurring order(s)', 0),
                ['date' => $date, 'recurrence_rules' => 0]
            );

            return;
        }

        $generationId = $generation->getId();
        $subscriptionIds = array_map(fn(Task\RecurrenceRule $subscription) => $subscription->getId(), $subscriptions);

        // Send a single recap notification for all the deliveries created below,
        // instead of one notification per delivery
        $this->deliveryCreatedNotifier->startBatch();

        try {
            foreach ($subscriptionIds as $subscriptionId) {
                $savepoint = $this->deliveryCreatedNotifier->savepoint();

                try {
                    $order = $this->createOrderInTransaction($subscriptionId, $date);
                } catch (\Throwable $e) {
                    // One bad rule should not block the other rules for the date.
                    // Failed rules are retried with the whole message, successes
                    // are skipped on retry via filterWithoutOrdersOnDate().
                    $this->deliveryCreatedNotifier->rollbackTo($savepoint);

                    // A rolled back transaction closes the entity manager, and
                    // leaves what it loaded detached
                    $this->doctrine->resetManager();
                    $generation = $this->generationRepository->find($generationId);

                    $generation->fail($subscriptionId, $e->getMessage());
                    $this->entityManager->flush();

                    $this->logger->error(
                        sprintf('Failed to generate recurring order: %s', $e->getMessage()),
                        ['date' => $date, 'recurrence_rule' => $subscriptionId]
                    );

                    continue;
                }

                if (!is_null($order)) {
                    $generation->succeed();
                    $this->entityManager->flush();
                }
            }

            $this->deliveryCreatedNotifier->endBatch();
        } catch (\Throwable $e) {
            // Unexpected failure (loading rules, dispatching recap, ...):
            // send nothing, so retries don't spam partial recaps.
            $this->deliveryCreatedNotifier->abortBatch();
            $generation->abort($e->getMessage());
            $this->entityManager->flush();

            throw $e;
        }

        $generation->finish();
        $this->entityManager->flush();

        if ($generation->getFailed() > 0) {
            throw new GenerateOrdersException(
                sprintf('Failed to generate recurring orders for %d rule(s): %s',
                    $generation->getFailed(),
                    implode(', ', array_map(fn(array $error) => $error['recurrence_rule'], $generation->getErrors())))
            );
        }

        $this->logger->info(
            sprintf('Generated %d recurring order(s)', $generation->getSucceeded()),
            ['date' => $date, 'recurrence_rules' => count($subscriptions)]
        );
    }

    /**
     * Each rule gets its own transaction: a worker that dies or a rule that fails
     * halfway leaves nothing behind - no tasks, no order stuck in "cart" - and
     * the rules done before it stay done. The live updates and notifications
     * about the order only go out once it is committed.
     */
    private function createOrderInTransaction(int $subscriptionId, string $date): ?OrderInterface
    {
        return $this->transactionalMessages->run(
            fn() => $this->entityManager->wrapInTransaction(function () use ($subscriptionId, $date) {
                /** @var Task\RecurrenceRule $subscription */
                $subscription = $this->entityManager->find(Task\RecurrenceRule::class, $subscriptionId);

                return $this->deliveryOrderManager->createOrderFromRecurrenceRule($subscription, $date);
            })
        );
    }

    /**
     * The run is recorded whether or not it was asked for over HTTP, so a
     * message dispatched by hand still leaves the same trace.
     */
    private function loadGeneration(string $date): RecurrenceRuleGeneration
    {
        $generation = $this->generationRepository->findOneByDate($date);

        if (is_null($generation)) {
            $generation = new RecurrenceRuleGeneration(new \DateTime($date));
            $this->entityManager->persist($generation);
        }

        return $generation;
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
