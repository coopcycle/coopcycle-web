<?php

namespace AppBundle\Service;

use AppBundle\Entity\StripePayment;
use Psr\Log\LoggerInterface;
use Stripe;

class StripeManager
{
    private $settingsManager;

    public function __construct(SettingsManager $settingsManager)
    {
        $this->settingsManager = $settingsManager;
    }

    /**
     * @return Stripe\Charge
     */
    public function authorize(StripePayment $stripePayment)
    {
        Stripe\Stripe::setApiKey($this->settingsManager->get('stripe_secret_key'));

        $livemode = $this->settingsManager->isStripeLivemode();

        $stripeToken = $stripePayment->getStripeToken();

        if (null === $stripeToken) {
            throw new \Exception('No Stripe token provided');
        }

        $order = $stripePayment->getOrder();
        $stripeAccount = $order->getRestaurant()->getStripeAccount($livemode);

        $stripeParams = [
            'amount' => $stripePayment->getAmount(),
            'currency' => strtolower($stripePayment->getCurrencyCode()),
            'source' => $stripeToken,
            'description' => sprintf('Order %s', $order->getNumber()),
            // To authorize a payment without capturing it,
            // make a charge request that also includes the capture parameter with a value of false.
            // This instructs Stripe to only authorize the amount on the customer’s card.
            'capture' => false
        ];

        $stripeOptions = [];

        if (!is_null($stripeAccount)) {

            $restaurantPaysStripeFee = $order->getRestaurant()->getContract()->isRestaurantPaysStripeFee();
            $applicationFee = $order->getFeeTotal();

            if ($restaurantPaysStripeFee) {
                // needed only when using direct charges (the charge is linked to the restaurant's Stripe account)
                $stripePayment->setStripeUserId($stripeAccount->getStripeUserId());
                $stripeOptions['stripe_account'] = $stripeAccount->getStripeUserId();
                $stripeParams['application_fee'] = $applicationFee;
            } else {
                $stripeParams['destination'] = array(
                    'account' => $stripeAccount->getStripeUserId(),
                    'amount' => $order->getTotal() - $applicationFee
                );
            }
        }

        return Stripe\Charge::create(
            $stripeParams,
            $stripeOptions
        );
    }

    /**
     * @return Stripe\Charge
     */
    public function capture(StripePayment $stripePayment)
    {
        Stripe\Stripe::setApiKey($this->settingsManager->get('stripe_secret_key'));

        $stripeAccount = $stripePayment->getStripeUserId();
        $stripeOptions = array();

        // stripe account & needed is set if and only the Stripe charge is a direct charge (restaurant pays stripe fee)
        if (!is_null($stripeAccount)) {
            $stripeOptions['stripe_account'] = $stripeAccount;
        }

        $charge = Stripe\Charge::retrieve(
            $stripePayment->getCharge(),
            $stripeOptions
        );

        if ($charge->captured) {
            // FIXME
            // If we land here, there is a severe problem
            throw new \Exception('Charge already captured');
        }

        $charge->capture();

        return $charge;
    }
}
