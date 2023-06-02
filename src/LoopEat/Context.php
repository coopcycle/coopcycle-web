<?php

namespace AppBundle\LoopEat;

use AppBundle\LoopEat\GuestCheckoutAwareAdapter as LoopEatAdapter;
use AppBundle\Sylius\Order\OrderInterface;
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

    public $logoUrl;
    public $name;
    public $customerAppUrl;
    public $hasCredentials = false;
    public $formats = [];

    public $creditsCountCents = 0;
    public $containersCount = 0;
    public $requiredAmount = 0;
    public $containers = [];

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

        $initiative = $this->client->initiative();

        $this->logoUrl = $initiative['logo_url'];
        $this->name = $initiative['name'];
        $this->customerAppUrl = $initiative['customer_app_url'];
        $this->formats = $this->client->getFormats($order->getRestaurant());

        $adapter = new LoopEatAdapter($order, $this->requestStack->getSession());

        if ($adapter->hasLoopEatCredentials()) {

            try {

                $currentCustomer = $this->client->currentCustomer($adapter);

                $this->hasCredentials = true;
                $this->creditsCountCents = $currentCustomer['credits_count_cents'];
                $this->containersCount = $currentCustomer['containers_count'];
                $this->requiredAmount = $order->getRequiredAmountForLoopeat();

                if ($currentCustomer['containers_count'] > 0) {
                    $this->containers = $this->client->listContainers($adapter);
                }

                $this->logger->info(sprintf('LoopEat context for order #%d successfully initialized', $order->getId()));

            } catch (RequestException $e) {
                $this->logger->error($e->getMessage());
            }

        } else {
            $this->logger->info(sprintf('No LoopEat credentials found for order #%d', $order->getId()));
        }
    }

    public function getAuthorizationUrl(OrderInterface $order, int $requiredCredits = 0)
    {
        $params = [
            'state' => $this->client->createStateParamForOrder($order),
        ];

        if ($requiredCredits > 0) {
            $params['required_credits_cents'] = $requiredCredits;
        }

        return $this->client->getOAuthAuthorizeUrl($params);
    }
}
