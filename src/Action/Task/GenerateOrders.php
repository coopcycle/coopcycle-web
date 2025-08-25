<?php

namespace AppBundle\Action\Task;

use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Task;
use AppBundle\Service\DeliveryOrderManager;
use Carbon\Carbon;
use Doctrine\ORM\EntityManagerInterface;
use Recurr\Transformer\ArrayTransformer;
use Recurr\Transformer\Constraint\BetweenConstraint;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class GenerateOrders
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DeliveryOrderManager $deliveryOrderManager,
    )
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

        if (Carbon::parse($date . ' 23:59')->isPast()) {
            throw new BadRequestHttpException('Date must be in the future');
        }

        $this->entityManager->getFilters()->enable('soft_deleteable');
        $allSubscriptions = $this->entityManager->getRepository(Task\RecurrenceRule::class)->findByGenerateOrders(true);
        $this->entityManager->getFilters()->disable('soft_deleteable');

        $subscriptions = array_filter($allSubscriptions, function ($subscription) use ($date) {
            return $this->filterByDate($subscription, $date);
        });

        $subscriptions = array_filter($subscriptions, function ($subscription) use ($date) {
            return $this->filterWithoutOrdersOnDate($subscription, $date);
        });

        $orders = [];

        foreach ($subscriptions as $subscription) {
            $order = $this->deliveryOrderManager->createOrderFromRecurrenceRule($subscription, $date);
            if (null !== $order) {
                $orders[] = $order;
            }
        }

        return $orders;
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

        $orders = $this->entityManager->getRepository(Order::class)->findBySubscriptionAndDate($subscription, $date);

        return 0 === count($orders);
    }
}
