<?php

namespace AppBundle\Utils;

use Symfony\Component\Validator\Constraints as Assert;
use Misd\PhoneNumberBundle\Validator\Constraints\PhoneNumber as AssertPhoneNumber;

class Settings
{
    public $brand_name;

    public $administrator_email;

    /**
     * @AssertPhoneNumber
     */
    public $phone_number;

    /**
     * @Assert\Regex("/^pk_test_[A-Za-z0-9]+/")
     */
    public $stripe_test_publishable_key;

    /**
     * @Assert\Regex("/^sk_test_[A-Za-z0-9]+/")
     */
    public $stripe_test_secret_key;

    /**
     * @Assert\Regex("/^ca_[A-Za-z0-9]+/")
     */
    public $stripe_test_connect_client_id;

    /**
     * @Assert\Regex("/^pk_live_[A-Za-z0-9]+/")
     */
    public $stripe_live_publishable_key;

    /**
     * @Assert\Regex("/^sk_live_[A-Za-z0-9]+/")
     */
    public $stripe_live_secret_key;

    /**
     * @Assert\Regex("/^ca_[A-Za-z0-9]+/")
     */
    public $stripe_live_connect_client_id;

    public $sms_enabled;

    public $sms_gateway;

    public $sms_gateway_config;

    /**
     * @Assert\Choice({"yes", "no"})
     */
    public $stripe_livemode;

    public $google_api_key;

    public $latlng;

    public $subject_to_vat;

    public $currency_code;

    /**
     * @Assert\Choice({"yes", "no"})
     */
    public $enable_restaurant_pledges;

    /**
     * @Assert\Regex("/^TEST-[A-Za-z0-9-]+/")
     */
    public $mercadopago_test_publishable_key;

    /**
     * @Assert\Regex("/^TEST-[A-Za-z0-9-]+/")
     */
    public $mercadopago_test_access_token;

    /**
     * @Assert\Regex("/^APP_USR-[A-Za-z0-9-]+/")
     */
    public $mercadopago_live_publishable_key;

    /**
     * @Assert\Regex("/^APP_USR-[A-Za-z0-9-]+/")
     */
    public $mercadopago_live_access_token;

    public $mercadopago_app_id;

    public $mercadopago_client_secret;

    public $guest_checkout_enabled;

    public $autocomplete_provider;

    /**
     * The regex to validate Google API Key was found on https://github.com/odomojuli/RegExAPI
     *
     * @Assert\Expression(
     *   "this.autocomplete_provider != 'google' or this.geocoding_provider != 'google' or value != ''",
     *   message="This value should not be blank."
     * )
     * @Assert\Regex("/AIza[0-9A-Za-z-_]{35}/")
     */
    public $google_api_key_custom;

    public $geocoding_provider;
}
