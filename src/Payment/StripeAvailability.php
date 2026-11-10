<?php

namespace AppBundle\Payment;

use AppBundle\Entity\LocalBusiness;
use Craue\ConfigBundle\Util\Config as CraueConfig;

/**
 * Tells whether Stripe can actually be used to process a card payment,
 * both at platform level (API keys) & at shop level (Stripe Connect account).
 *
 * It depends on CraueConfig directly, and *not* on SettingsManager, on purpose:
 * SettingsManager depends on GatewayResolver, which depends on this class.
 */
class StripeAvailability
{
    public function __construct(
        private CraueConfig $craueConfig,
        private bool $stripeConnectRequired = true)
    {}

    /**
     * Stripe API keys are configured at platform level.
     */
    public function isConfigured(): bool
    {
        return $this->canEnableTestmode() || $this->canEnableLivemode();
    }

    /**
     * Stripe can be used to charge a card for this shop.
     *
     * When Stripe Connect is not required, platform level keys are enough.
     */
    public function supportsRestaurant(?LocalBusiness $restaurant): bool
    {
        if (!$this->isConfigured()) {

            return false;
        }

        if (!$this->stripeConnectRequired) {

            return true;
        }

        if (null === $restaurant) {

            return false;
        }

        return null !== $restaurant->getStripeAccount($this->isLivemode());
    }

    public function isLivemode(): bool
    {
        return filter_var($this->getConfig('stripe_livemode'), FILTER_VALIDATE_BOOLEAN);
    }

    private function canEnableTestmode(): bool
    {
        return !empty($this->getConfig('stripe_test_publishable_key'))
            && !empty($this->getConfig('stripe_test_secret_key'))
            && !empty($this->getConfig('stripe_test_connect_client_id'));
    }

    private function canEnableLivemode(): bool
    {
        return !empty($this->getConfig('stripe_live_publishable_key'))
            && !empty($this->getConfig('stripe_live_secret_key'))
            && !empty($this->getConfig('stripe_live_connect_client_id'));
    }

    private function getConfig(string $name)
    {
        try {
            return $this->craueConfig->get($name);
        } catch (\RuntimeException $e) {
            return null;
        }
    }
}
