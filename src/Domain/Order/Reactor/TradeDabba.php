<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Dabba\Client as DabbaClient;
use AppBundle\Dabba\OAuthCredentialsInterface;
use AppBundle\Dabba\TradeException;
use AppBundle\Domain\Order\Event;
use AppBundle\Sylius\Customer\CustomerInterface;
use Psr\Log\LoggerInterface;
use Webmozart\Assert\Assert;

class TradeDabba
{
    private $client;
    private $logger;

    public function __construct(DabbaClient $client, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
    }

    public function __invoke(Event\OrderPicked $event)
    {
        $order = $event->getOrder();
        $restaurant = $order->getRestaurant();

        if (null === $restaurant) {
            return;
        }

        if (!$restaurant->isDabbaEnabled()) {
            return;
        }

        if (!$order->isReusablePackagingEnabled() || $order->getReusablePackagingQuantity() < 1) {
            return;
        }

        try {

            $response = $this->client->trade($order->getCustomer(), $restaurant->getDabbaCode(),
                $order->getReusablePackagingQuantity(), $order->getReusablePackagingPledgeReturn());

            $this->logger->info('Successfully completed Dabba trade');

            Assert::isInstanceOf($order->getCustomer(), CustomerInterface::class);
            Assert::isInstanceOf($order->getCustomer(), OAuthCredentialsInterface::class);

            // When this is a guest checkout, we clear the credentials after grabbing
            if (!$order->getCustomer()->hasUser()) {
                $order->getCustomer()->clearDabbaCredentials();
            }

        } catch (TradeException $e) {
            $this->logger->error(
                sprintf('Could not complete trade for order #%d. Dabba API returned error "%s"', $order->getId(), $e->getMessage()),
                ['exception' => $e]
            );
        }
    }
}
