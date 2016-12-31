<?php

namespace AppBundle\Service;

use AppBundle\Entity\Order;
use Stripe;
use Symfony\Component\HttpFoundation\Request;
use Psr\Log\LoggerInterface;

class PaymentService
{
    private $logger;

    public function __construct($apiKey, LoggerInterface $logger)
    {
        Stripe\Stripe::setApiKey($apiKey);

        $this->logger = $logger;
    }

    public function createCharge(Order $order, Request $request)
    {
        $params = [];
        $content = $request->getContent();
        if (!empty($content)){
            $params = json_decode($content, true);
        }

        $token = $params['stripeToken'];

        $this->logger->info($token);

        try {
            $charge = Stripe\Charge::create(array(
                "amount" => $order->getTotal() * 100, // Amount in cents
                "currency" => "eur",
                "source" => $token,
                "description" => "Order #".$order->getId()
            ));
        } catch (Stripe\Error\Card $e) {
            throw new \Exception($e);
        }
    }
}