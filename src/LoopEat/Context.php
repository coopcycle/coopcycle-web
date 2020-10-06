<?php

namespace AppBundle\LoopEat;

use GuzzleHttp\Exception\RequestException;
use Sylius\Component\Order\Context\CartContextInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Context
{
    private $client;
    private $cartContext;
    private $logger;

    private $loopeatBalance = 0;
    private $loopeatCredit = 0;

    public function __construct(Client $client, CartContextInterface $cartContext, LoggerInterface $logger = null)
    {
        $this->client = $client;
        $this->cartContext = $cartContext;
        $this->logger = $logger ?? new NullLogger();
    }

    public function initialize()
    {
        $order = $this->cartContext->getCart();

        if (null === $order || null === $order->getRestaurant()) {

            return;
        }

        $this->logger->info(sprintf('Initializing LoopEat context for order #%d', $order->getId()));

        $customer = $order->getCustomer();

        if (null === $customer || !$customer->hasUser()) {
            $this->logger->info(sprintf('Order #%d has no user data available', $order->getId()));
            return;
        }

        $user = $customer->getUser();

        if ($user->hasLoopEatCredentials()) {

            try {

                $loopeatCustomer = $this->client->currentCustomer($user);

                $this->loopeatBalance = $loopeatCustomer['loopeatBalance'];
                $this->loopeatCredit  = $loopeatCustomer['loopeatCredit'];

                $this->logger->info(sprintf('LoopEat context for order #%d successfully initialized', $order->getId()));

            } catch (RequestException $e) {
                $this->logger->error($e->getMessage());
            }

        } else {
            $this->logger->info(sprintf('Customer for order #%d has no LoopEat credentials', $order->getId()));
        }
    }

    public function getLoopeatBalance()
    {
        return $this->loopeatBalance;
    }

    public function getLoopeatCredit()
    {
        return $this->loopeatCredit;
    }
}
