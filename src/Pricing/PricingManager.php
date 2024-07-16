<?php

namespace AppBundle\Pricing;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Exception\Pricing\NoRuleMatchedException;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\OrderManager;
use AppBundle\Sylius\Order\OrderFactory;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * FIXME: Should we merge this class into the OrderManager class?
 */
class PricingManager
{
    public function __construct(
        private readonly DeliveryManager $deliveryManager,
        private readonly OrderManager $orderManager,
        private readonly OrderFactory $orderFactory,
        private readonly EntityManagerInterface $entityManager,
        private readonly DenormalizerInterface $denormalizer,
        private readonly LoggerInterface $logger)
    {}

    /**
     * @return OrderInterface|null
     */
    public function createOrder(Delivery $delivery, bool $throwException = false): ?OrderInterface
    {
        $store = $delivery->getStore();

        if (null !== $store && $store->getCreateOrders()) {

            $price = $this->deliveryManager->getPrice($delivery, $store->getPricingRuleSet());

            if (null === $price) {

                if ($throwException) {
                    throw new NoRuleMatchedException();
                }

                $this->logger->error('Price could not be calculated');

                return null;
            }

            $price = (int) $price;

            $order = $this->orderFactory->createForDelivery($delivery, $price);

            // We need to persist the order first,
            // because an auto increment is needed to generate a number
            $this->entityManager->persist($order);
            $this->entityManager->flush();

            $this->orderManager->onDemand($order);

            $this->entityManager->flush();

            return $order;
        }

        return null;
    }

    public function createOrderFromSubscription(Task\RecurrenceRule $subscription, string $startDate): ?OrderInterface
    {
        $store = $subscription->getStore();

        $template = $subscription->getTemplate();
        $tasksTemplates = $template['@type'] === 'hydra:Collection' ?
            $template['hydra:member'] : [ $template ];

        $tasksTemplates = array_map(function ($taskTemplate) use ($startDate) {

            $taskTemplate['after'] = (new \DateTime($startDate . ' ' . $taskTemplate['after']))->format(\DateTime::ATOM);
            $taskTemplate['before'] = (new \DateTime($startDate . ' ' . $taskTemplate['before']))->format(\DateTime::ATOM);

            return $taskTemplate;
        }, $tasksTemplates);


        $tasks = [];
        foreach ($tasksTemplates as $payload) {

            $task = $this->denormalizer->denormalize($payload, Task::class, 'jsonld', [
                'groups' => ['task_create']
            ]);

            $task->setOrganization($store->getOrganization());
            $task->setRecurrenceRule($subscription);
            $this->entityManager->persist($task);
            $tasks[] = $task;
        }

        $order = null;
        if (count($tasks) > 1 && $tasks[0]->isPickup()) {
            $delivery = Delivery::createWithTasks(...$tasks);
            $store->addDelivery($delivery);
            $this->entityManager->persist($delivery);

            $order = $this->createOrder($delivery);
            if (null !== $order) {
                $order->setSubscription($subscription);
            }
        }

        $this->entityManager->flush();

        return $order;
    }
}
