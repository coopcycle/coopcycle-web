<?php

namespace AppBundle\Action\Task;

use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Task;
use AppBundle\Pricing\PricingManager;
use Doctrine\ORM\EntityManagerInterface;
use Recurr\Transformer\ArrayTransformer;
use Recurr\Transformer\Constraint\BetweenConstraint;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class GenerateOrders
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PricingManager $pricingManager)
    {
    }

    public function __invoke($data, Request $request)
    {
        //get query parameters
        $queryParams = $request->query->all();

        $date = $queryParams['date'];

        if (empty($date)) {
            throw new BadRequestHttpException('Date is required');
        }

        $allSubscriptions = $this->entityManager->getRepository(Task\RecurrenceRule::class)->findAll();

        $subscriptions = array_filter($allSubscriptions, function ($subscription) use ($date) {
            return $this->filterByDate($subscription, $date);
        });

        $subscriptions = array_filter($subscriptions, function ($subscription) use ($date) {
            return $this->filterWithoutOrdersOnDate($subscription, $date);
        });

        $orders = [];

        foreach ($subscriptions as $subscription) {
            $order = $this->pricingManager->createOrderFromSubscription($subscription, $date);
            if (null !== $order) {
                $orders[] = $order;
            }
        }

        return $orders;
    }

    private function filterByDate(Task\RecurrenceRule $subscription, string $startDate): bool
    {
        $after = new \DateTime($startDate . ' 00:00');
        $before = new \DateTime($startDate . ' 23:59');

        $transformer = new ArrayTransformer();
        $constraint = new BetweenConstraint(
            $after,
            $before,
            $inc = true
        );

        $rule = $subscription->getRule();

        $rule->setStartDate($after);
        $rule->setEndDate($before);

        $occurrences = $transformer->transform($rule, $constraint);

        return count($occurrences) > 0;
    }

    private function filterWithoutOrdersOnDate(Task\RecurrenceRule $subscription, string $startDate): bool
    {
        $date = new \DateTime($startDate . ' 00:00');

        $orders = $this->entityManager->getRepository(Order::class)->findBySubscriptionAndDate($subscription, $date);

        // Ideally, we should create an order for each subscription,
        // but previously we were creating only tasks sometimes and this behavior is still supported,
        // that's why we need to check for tasks as well
        $tasks = $this->entityManager->getRepository(Task::class)->findBySubscriptionAndDate($subscription, $date);

        return 0 === count($orders) && 0 === count($tasks);
    }
}
