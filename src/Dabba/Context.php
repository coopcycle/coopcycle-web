<?php

namespace AppBundle\Dabba;

use AppBundle\Dabba\GuestCheckoutAwareAdapter as DabbaAdapter;
use AppBundle\Sylius\Order\OrderInterface;
use GuzzleHttp\Exception\RequestException;
use Sylius\Component\Order\Context\CartContextInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class Context
{
    private $client;
    private $cartContext;
    private $logger;

    private $wallet = 0;
    private $containers = [];
    private $userContainers = [];

    public function __construct(
        Client $client,
        CartContextInterface $cartContext,
        SessionInterface $session,
        CacheInterface $appCache,
        LoggerInterface $logger = null)
    {
        $this->client = $client;
        $this->cartContext = $cartContext;
        $this->session = $session;
        $this->appCache = $appCache;
        $this->logger = $logger ?? new NullLogger();
    }

    public function initialize()
    {
        $order = $this->cartContext->getCart();

        if (null === $order || !$order->hasVendor() || $order->isMultiVendor()) {

            return;
        }

        $this->logger->info(sprintf('Initializing Dabba context for order #%d', $order->getId()));

        $this->containers = $this->appCache->get('dabba.containers', function (ItemInterface $item) {

            $item->expiresAfter(60 * 60);

            return $this->client->containers();
        });

        $adapter = new DabbaAdapter($order, $this->session);

        if ($adapter->hasDabbaCredentials()) {

            try {

                $currentUser = $this->client->currentUser($adapter);

                $this->wallet = $currentUser['wallet'];
                $this->userContainers = $currentUser['containers'];

                $this->logger->info(sprintf('Context for order #%d successfully initialized', $order->getId()));

            } catch (HttpExceptionInterface $e) {
                $this->logger->error($e->getMessage());
            }

        } else {
            $this->logger->info(sprintf('No credentials found for order #%d', $order->getId()));
        }
    }

    public function getWallet()
    {
        return $this->wallet;
    }

    public function getUnitPrice()
    {
        $container = current($this->containers);

        return $container['price'];
    }

    public function getContainers(): int
    {
        $container = current($this->containers);

        if (!empty($this->userContainers)) {
            return $this->userContainers[$container['id']];
        }

        return 0;
    }

    public function getMissing(OrderInterface $order): int
    {
        // TODO Implement returns
        // {% set missing = (((order.reusablePackagingQuantity - pledge_return) * dabba_context.unitPrice) - dabba_context.wallet) %}
        return ($order->getReusablePackagingQuantity() * $this->getUnitPrice()) - $this->getWallet();
    }
}
