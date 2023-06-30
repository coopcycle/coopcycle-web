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

        $adapter = new GuestCheckoutAwareAdapter($order);

        if ($adapter->hasLoopEatCredentials()) {

            try {

                $currentCustomer = $this->client->currentCustomer($adapter);

                $context->hasCredentials = true;
                $context->creditsCountCents = $currentCustomer['credits_count_cents'];
                $context->containersCount = $currentCustomer['containers_count'];
                $context->requiredAmount = $order->getRequiredAmountForLoopeat();

                if ($currentCustomer['containers_count'] > 0) {
                    $context->containers = $this->client->listContainers($adapter);
                }

                $this->logger->info(sprintf('Loopeat context for order #%d successfully initialized', $order->getId()));

            } catch (RequestException $e) {
                $this->logger->error($e->getMessage());
            }

        } else {
            $this->logger->info(sprintf('No Loopeat credentials found for order #%d', $order->getId()));
        }

        return $context;
    }
}
