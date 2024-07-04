<?php

namespace AppBundle\Utils;

use Symfony\Component\Validator\Constraints as Assert;
use Misd\PhoneNumberBundle\Validator\Constraints\PhoneNumber as AssertPhoneNumber;
use AppBundle\Validator\Constraints\GoogleApiKey as AssertGoogleApiKey;

class Settings
{
    /**
     * @Assert\NotBlank(groups={"Default", "mandatory"})
     */
    public $brand_name;

    /**
     * @Assert\NotBlank(groups={"Default", "mandatory"})
     * @Assert\Email(groups={"Default", "mandatory"})
     */
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

    /**
     * @Assert\Expression(
     *   "!this.sms_enabled or value in ['mailjet', 'twilio']",
     *   message="This value should not be blank."
     * )
     */
    public $sms_gateway;

    public $sms_gateway_config;

    /**
     * @Assert\Choice({"yes", "no"})
     */
    public $stripe_livemode;

    /**
     * @Assert\NotBlank(groups={"Default", "mandatory"})
     */
    public $latlng;

    public $subject_to_vat;

    /**
     * @Assert\NotBlank(groups={"Default", "mandatory"})
     */
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

    public $company_legal_name;

    public $company_legal_id;

    /**
     * @AssertGoogleApiKey()
     */
    public $google_api_key_custom;

    public $geocoding_provider;
}
