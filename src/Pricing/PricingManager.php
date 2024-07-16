<?php

namespace AppBundle\Pricing;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Sylius\ArbitraryPrice;
use AppBundle\Entity\Sylius\PricingRulesBasedPrice;
use AppBundle\Entity\Sylius\UseArbitraryPrice;
use AppBundle\Entity\Sylius\PricingStrategy;
use AppBundle\Entity\Sylius\UsePricingRules;
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
    public function createOrder(Delivery $delivery, PricingStrategy $pricingStrategy = null, bool $throwException = false): ?OrderInterface
    {
        // Defining a default value in the method signature fails in the phpunit tests
        // even though it seems that it was fixed: https://github.com/sebastianbergmann/phpunit/commit/658d8decbec90c4165c0b911cf6cfeb5f6601cae
        if (null === $pricingStrategy) {
            $pricingStrategy = new UsePricingRules();
        }

        $store = $delivery->getStore();

        if (null !== $store && $store->getCreateOrders()) {

            $order = null;

            if ($pricingStrategy instanceof UsePricingRules) {
                $price = $this->deliveryManager->getPrice($delivery, $store->getPricingRuleSet());

                if (null === $price) {

                    if ($throwException) {
                        throw new NoRuleMatchedException();
                    }

                    $this->logger->error('Price could not be calculated');

                    return null;
                }

                $price = (int) $price;
                $order = $this->orderFactory->createForDelivery($delivery, new PricingRulesBasedPrice($price));

            } elseif ($pricingStrategy instanceof UseArbitraryPrice) {
                $order = $this->orderFactory->createForDelivery($delivery, new ArbitraryPrice($pricingStrategy->getVariantName(), $pricingStrategy->getVariantPrice()));

            } else {
                if ($throwException) {
                    throw new \InvalidArgumentException('Unsupported pricing config');
                }
            }

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

            if ($arbitraryPriceTemplate = $subscription->getArbitraryPriceTemplate()) {
                $order = $this->createOrder($delivery, new UseArbitraryPrice($arbitraryPriceTemplate['variantName'], $arbitraryPriceTemplate['variantPrice']));
            } else {
                $order = $this->createOrder($delivery);
            }

            if (null !== $order) {
                $order->setSubscription($subscription);
            }
        }

        $this->entityManager->flush();

        return $order;
    }
}
