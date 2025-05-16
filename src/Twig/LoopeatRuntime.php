<?php

namespace AppBundle\Twig;

use AppBundle\LoopEat\Client;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\OrderProcessing\OrderDepositRefundProcessor;
use Twig\Extension\RuntimeExtensionInterface;

class LoopeatRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private Client $client,
        private OrderDepositRefundProcessor $orderDepositRefundProcessor)
    {}

    public function getAuthorizationUrl(OrderInterface $order, int $requiredCredits = 0, bool $showDeposit = false)
    {
        $params = [
            'state' => $this->client->createStateParamForOrder($order),
        ];

        if ($requiredCredits > 0) {
            $params['required_credits_cents'] = $requiredCredits;
        }

        if ($showDeposit) {
            $params['show_deposit'] = 'true';
        }

        return $this->client->getOAuthAuthorizeUrl($params);
    }

    public function getName()
    {
        $initiative = $this->client->initiative();

        return $initiative['name'];
    }

    public function getReturnsFee(OrderInterface $order): int
    {
        return $this->orderDepositRefundProcessor->getLoopeatReturnsFee($order);
    }
}
