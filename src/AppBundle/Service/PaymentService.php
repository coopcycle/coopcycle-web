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

    private function getTransferGroup(Order $order)
    {
        return sprintf('order#%d', $order->getId());
    }

    public function authorize(Order $order, $stripeToken)
    {
        $this->logger->info('Authorizing payment for order #' . $order->getId());

        // @link https://stripe.com/docs/charges#auth-and-capture
        $charge = Stripe\Charge::create(array(
            'amount' => $order->getTotal() * 100, // Amount in cents
            'currency' => 'eur',
            'source' => $stripeToken,
            'description' => 'Order #'.$order->getId(),
            'transfer_group' => $this->getTransferGroup($order),
            // To authorize a payment without capturing it,
            // make a charge request that also includes the capture parameter with a value of false.
            // This instructs Stripe to only authorize the amount on the customer’s card.
            'capture' => false,
        ));

        $order->setCharge($charge->id);
    }

    public function capture(Order $order)
    {
        $this->logger->info('Capturing payment for order #' . $order->getId());

        // TODO Check if $order->getCharge() is NULL

        $charge = Stripe\Charge::retrieve($order->getCharge());

        if (!$charge->captured) {
            $charge->capture();
        }

        // TODO Create a Transfer
    }
}
