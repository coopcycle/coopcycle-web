<?php

namespace AppBundle\Service;

use AppBundle\Utils\Settings;
use Craue\ConfigBundle\Util\Config as CraueConfig;
use Doctrine\Persistence\ManagerRegistry;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
use Psr\Log\LoggerInterface;
use AppBundle\Payment\GatewayResolver;

class SettingsManager
{
    private $craueConfig;
    private $configEntityName;
    private $phoneNumberUtil;
    private $country;
    private $doctrine;
    private $logger;
    private $gatewayResolver;

    private $mandatorySettings = [
        'brand_name',
        'administrator_email',
        'google_api_key',
        'latlng',
        'currency_code',
    ];

    private $secretSettings = [
        'stripe_test_publishable_key',
        'stripe_test_secret_key',
        'stripe_test_connect_client_id',
        'stripe_live_publishable_key',
        'stripe_live_secret_key',
        'stripe_live_connect_client_id',
        'payment_gateway',
        'payment_method_publishable_key',
        'google_api_key',
        'mercadopago_test_publishable_key',
        'mercadopago_live_publishable_key',
        'mercadopago_test_access_token',
        'mercadopago_live_access_token',
    ];

    private static $boolean = [
        'sms_enabled',
        'subject_to_vat',
        'guest_checkout_enabled',
    ];

    private $cache = [];

    public function __construct(
        CraueConfig $craueConfig,
        string $configEntityName,
        ManagerRegistry $doctrine,
        PhoneNumberUtil $phoneNumberUtil,
        string $country,
        bool $foodtechEnabled,
        bool $b2bEnabled,
        LoggerInterface $logger,
        GatewayResolver $gatewayResolver)
    {
        $this->craueConfig = $craueConfig;
        $this->configEntityName = $configEntityName;
        $this->doctrine = $doctrine;
        $this->phoneNumberUtil = $phoneNumberUtil;
        $this->country = $country;
        $this->foodtechEnabled = $foodtechEnabled;
        $this->b2bEnabled = $b2bEnabled;
        $this->logger = $logger;
        $this->gatewayResolver = $gatewayResolver;
    }

    public function isSecret($name)
    {
        return in_array($name, $this->secretSettings);
    }

    public function get($name)
    {
        switch ($name) {
            case 'payment_gateway':
                return $this->gatewayResolver->resolve();
            case 'stripe_publishable_key':
                $name = $this->isStripeLivemode() ? 'stripe_live_publishable_key' : 'stripe_test_publishable_key';
                break;
            case 'stripe_secret_key':
                $name = $this->isStripeLivemode() ? 'stripe_live_secret_key' : 'stripe_test_secret_key';
                break;
            case 'stripe_connect_client_id':
                $name = $this->isStripeLivemode() ? 'stripe_live_connect_client_id' : 'stripe_test_connect_client_id';
                break;
            case 'mercadopago_publishable_key':
                $name = $this->isMercadopagoLivemode() ? 'mercadopago_live_publishable_key' : 'mercadopago_test_publishable_key';
                break;
            case 'mercadopago_access_token':
                $name = $this->isMercadopagoLivemode() ? 'mercadopago_live_access_token' : 'mercadopago_test_access_token';
                break;
            case 'timezone':
                return ini_get('date.timezone');
            case 'foodtech_enabled':
                return $this->foodtechEnabled;
            case 'b2b_enabled':
                return $this->b2bEnabled;
        }

        if (isset($this->cache[$name])) {

            return $this->cache[$name];
        }

        try {

            $value = $this->craueConfig->get($name);

            switch ($name) {
                case 'phone_number':
                    try {
                        $value = $this->phoneNumberUtil->parse($value, strtoupper($this->country));
                    } catch (NumberParseException $e) {}
                    break;
            }

            if (in_array($name, self::$boolean)) {
                $value = (bool) $value;
            }

            $this->cache[$name] = $value;

            return $value;

        } catch (\RuntimeException $e) {}
    }

    public function getBoolean($name)
    {
        return filter_var($this->get($name), FILTER_VALIDATE_BOOLEAN);
    }

    public function isStripeLivemode()
    {
        $livemode = $this->get('stripe_livemode');

        if (!$livemode) {
            return false;
        }

        return filter_var($livemode, FILTER_VALIDATE_BOOLEAN);
    }

    public function isMercadopagoLivemode()
    {
        $livemode = $this->get('mercadopago_livemode');

        if (!$livemode) {
            return false;
        }

        return filter_var($livemode, FILTER_VALIDATE_BOOLEAN);
    }

    public function canEnableStripeTestmode()
    {
        try {
            $stripeTestPublishableKey = $this->craueConfig->get('stripe_test_publishable_key');
            $stripeTestSecretKey = $this->craueConfig->get('stripe_test_secret_key');
            $stripeTestConnectClientId = $this->craueConfig->get('stripe_test_connect_client_id');

            return !empty($stripeTestPublishableKey) && !empty($stripeTestSecretKey) && !empty($stripeTestConnectClientId);

        } catch (\RuntimeException $e) {
            return false;
        }
    }

    public function canEnableStripeLivemode()
    {
        try {
            $stripeLivePublishableKey = $this->craueConfig->get('stripe_live_publishable_key');
            $stripeLiveSecretKey = $this->craueConfig->get('stripe_live_secret_key');
            $stripeLiveConnectClientId = $this->craueConfig->get('stripe_live_connect_client_id');

            return !empty($stripeLivePublishableKey) && !empty($stripeLiveSecretKey) && !empty($stripeLiveConnectClientId);

        } catch (\RuntimeException $e) {
            return false;
        }
    }

    public function canEnableMercadopagoTestmode()
    {
        try {
            $mercadopagoTestPublishableKey = $this->craueConfig->get('mercadopago_test_publishable_key');
            $mercadopagoTestSecretKey = $this->craueConfig->get('mercadopago_test_access_token');

            return !empty($mercadopagoTestPublishableKey) && !empty($mercadopagoTestSecretKey);

        } catch (\RuntimeException $e) {
            return false;
        }
    }

    public function canEnableMercadopagoLivemode()
    {
        try {
            $mercadopagoLivePublishableKey = $this->craueConfig->get('mercadopago_live_publishable_key');
            $mercadopagoLiveSecretKey = $this->craueConfig->get('mercadopago_live_access_token');

            return !empty($mercadopagoLivePublishableKey) && !empty($mercadopagoLiveSecretKey);

        } catch (\RuntimeException $e) {
            return false;
        }
    }

    public function canSendSms()
    {
        if (!$this->get('sms_enabled')) {

            return false;
        }

        $smsGateway = $this->get('sms_gateway');

        if (!$smsGateway || !in_array($smsGateway, ['mailjet', 'twilio'])) {

            return false;
        }

        $smsGatewayConfig = $this->get('sms_gateway_config');

        if (empty($smsGatewayConfig)) {

            return false;
        }

        $smsGatewayConfig = json_decode($smsGatewayConfig, true);

        if (empty($smsGatewayConfig)) {

            return false;
        }

        switch ($smsGateway) {
            case 'mailjet':
                return in_array($this->country, ['be', 'es', 'de', 'fr'])
                    && isset($smsGatewayConfig['api_token']);
            case 'twilio':
                return isset(
                    $smsGatewayConfig['sid'],
                    $smsGatewayConfig['auth_token'],
                    $smsGatewayConfig['from']
                );
        }

        return false;
    }

    public function set($name, $value, $section = null)
    {
        $className = $this->configEntityName;

        $params = [
            'name' => $name,
        ];

        if (!empty($section)) {
            $params['section'] = $section;
        }

        $setting = $this->doctrine
            ->getRepository($className)
            ->findOneBy($params);

        if (!$setting) {

            $setting = new $className();
            $setting->setName($name);
            $setting->setSection($section ?? 'general');

            $this->doctrine
                ->getManagerForClass($className)
                ->persist($setting);
        }

        if (isset($this->cache[$name])) {
            unset($this->cache[$name]);
        }

        $setting->setValue($value);
    }

    public function flush()
    {
        $this->doctrine->getManagerForClass($this->configEntityName)->flush();
    }

    public function isFullyConfigured()
    {
        foreach ($this->mandatorySettings as $name) {
            try {
                $value = $this->craueConfig->get($name);
                if (null === $value) {
                    return false;
                }
            } catch (\RuntimeException $e) {
                return false;
            }
        }

        return true;
    }

    public function asEntity()
    {
        $settings = new Settings();

        $keys = array_keys(get_object_vars($settings));

        foreach ($keys as $name) {
            try {
                $value = $this->craueConfig->get($name);

                if (in_array($name, self::$boolean)) {
                    $value = (bool) $value;
                }

                $settings->$name = $value;
            } catch (\RuntimeException $e) {}
        }

        return $settings;
    }
}
