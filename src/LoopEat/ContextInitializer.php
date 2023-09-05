<?php

namespace AppBundle\LoopEat;

use AppBundle\Sylius\Order\OrderInterface;
use GuzzleHttp\Exception\RequestException;
use Sylius\Component\Order\Context\CartContextInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\RequestStack;

class ContextInitializer
{
    private $client;
    private $logger;

    public function __construct(
        Client $client,
        LoggerInterface $logger = null)
    {
        $this->client = $client;
        $this->logger = $logger ?? new NullLogger();
    }

    public function initialize(OrderInterface $order, Context $context = null)
    {
        if (null === $context) {
        	$context = new Context();
        }

        $this->logger->info(sprintf('Initializing Loopeat context for order #%d', $order->getId()));

        $initiative = $this->client->initiative();

        $context->logoUrl = $initiative['logo_url'];
        $context->name = $initiative['name'];
        $context->customerAppUrl = $initiative['customer_app_url'];
        $context->formats = $this->client->getFormats($order->getRestaurant());
        $context->returns = $order->getLoopeatReturns();
        $context->returnsTotalAmount = $order->getReturnsAmountForLoopeat();
        $context->returnsCount = $order->getLoopeatReturnsCount();
        $context->requiredAmount = $order->getRequiredAmountForLoopeat();

        $adapter = new GuestCheckoutAwareAdapter($order);

        if ($adapter->hasLoopEatCredentials()) {

            try {

                $currentCustomer = $this->client->currentCustomer($adapter);

                $context->hasCredentials = true;
                $context->creditsCountCents = $currentCustomer['credits_count_cents'];
                $context->containersCount = $currentCustomer['containers_count'];

                if ($currentCustomer['containers_count'] > 0) {
                    $context->containers = $this->client->listContainers($adapter);
                }

                $context->containersTotalAmount = $this->getContainersTotalAmount($context->containers, $context->formats);

                $context->suggestion = $context->suggest($order);

                $this->logger->info(sprintf('Loopeat context for order #%d successfully initialized', $order->getId()));

            } catch (RequestException $e) {
                $this->logger->error($e->getMessage());
            }

        } else {
            $this->logger->info(sprintf('No Loopeat credentials found for order #%d', $order->getId()));
        }

        return $context;
    }

    private function getContainersTotalAmount(array $containers, array $formats)
    {
        // Index formats by id
        $formatsById = array_column($formats, 'cost_cents', 'id');

        return array_reduce($containers, function ($amount, $container) use ($formatsById) {
            return $amount + (($formatsById[$container['format_id']] ?? 0) * $container['quantity']);
        }, 0);
    }
}
