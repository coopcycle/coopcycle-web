<?php

namespace AppBundle\LoopEat;

use AppBundle\LoopEat\GuestCheckoutAwareAdapter as LoopEatAdapter;
use GuzzleHttp\Exception\RequestException;
use Sylius\Component\Order\Context\CartContextInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\RequestStack;

class Context
{
    private $client;
    private $cartContext;
    private $requestStack;
    private $logger;

    private $loopeatBalance = 0;
    private $loopeatCredit = 0;

    public function __construct(
        Client $client,
        CartContextInterface $cartContext,
        RequestStack $requestStack,
        LoggerInterface $logger = null)
    {
        $this->client = $client;
        $this->cartContext = $cartContext;
        $this->requestStack = $requestStack;
        $this->logger = $logger ?? new NullLogger();
    }

    public function initialize()
    {
        $order = $this->cartContext->getCart();

        if (null === $order || !$order->hasVendor() || $order->isMultiVendor()) {

            return;
        }

        $this->logger->info(sprintf('Initializing LoopEat context for order #%d', $order->getId()));

        $adapter = new LoopEatAdapter($order, $this->requestStack->getSession());

        if ($adapter->hasLoopEatCredentials()) {

            try {

                $loopeatCustomer = $this->client->currentCustomer($adapter);

                $this->loopeatBalance = $loopeatCustomer['loopeatBalance'];
                $this->loopeatCredit  = $loopeatCustomer['loopeatCredit'];

                $this->logger->info(sprintf('LoopEat context for order #%d successfully initialized', $order->getId()));

            } catch (RequestException $e) {
                $this->logger->error($e->getMessage());
            }

        } else {
            $this->logger->info(sprintf('No LoopEat credentials found for order #%d', $order->getId()));
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
