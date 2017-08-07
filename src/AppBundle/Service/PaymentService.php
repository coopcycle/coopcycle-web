<?php

namespace AppBundle\Service;

use AppBundle\Entity\Order;
use Stripe;
use Psr\Log\LoggerInterface;

class PaymentService
{
    private $logger;

    public function __construct($apiKey, LoggerInterface $logger)
    {
        Stripe\Stripe::setApiKey($apiKey);

        $this->logger = $logger;
    }

    public function createCharge(Order $order, $stripeToken)
    {
        $this->logger->info('Stripe token: ' . $stripeToken);

        try {

            $transferGroup = "Order#".$order->getId();

            $charge = Stripe\Charge::create(array(
                "amount" => $order->getTotal() * 100, // Amount in cents
                "currency" => "eur",
                "source" => $stripeToken,
                "description" => "Order #".$order->getId(),
                "transfer_group" => $transferGroup,
            ));

            // Create a Transfer to a connected account (later)
            $stripeParams = $order->getRestaurant()->getStripeParams();

            // FIXME This should be mandatory
            if ($stripeParams && $stripeParams->getUserId()) {

                $transfer = \Stripe\Transfer::create(array(
                  "amount" => (($order->getTotal() * 100) * 0.75),
                  "currency" => "eur",
                  "destination" => $stripeParams->getUserId(),
                  "transfer_group" => $transferGroup,
                ));
            }

        } catch (Stripe\Error\Card $e) {
            throw new \Exception($e);
        }
    }
}
