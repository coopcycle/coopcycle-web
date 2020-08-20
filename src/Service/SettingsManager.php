<?php

namespace AppBundle\Service;

use AppBundle\Utils\Settings;
use Craue\ConfigBundle\Util\Config as CraueConfig;
use Doctrine\Persistence\ManagerRegistry;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
use Psr\Log\LoggerInterface;

class SettingsManager
{
    private $craueConfig;
    private $configEntityName;
    private $phoneNumberUtil;
    private $country;
    private $doctrine;
    private $logger;

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
        'google_api_key',
        'mercadopago_test_publishable_key',
        'mercadopago_live_publishable_key',
        'mercadopago_test_access_token',
        'mercadopago_live_access_token',
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
        LoggerInterface $logger)
    {
        $this->craueConfig = $craueConfig;
        $this->configEntityName = $configEntityName;
        $this->doctrine = $doctrine;
        $this->phoneNumberUtil = $phoneNumberUtil;
        $this->country = $country;
        $this->foodtechEnabled = $foodtechEnabled;
        $this->b2bEnabled = $b2bEnabled;
        $this->logger = $logger;
    }

    public function isSecret($name)
    {
        return in_array($name, $this->secretSettings);
    }

    public function get($name)
    {
        switch ($name) {
            case 'stripe_publishable_key':
                $name = $this->isStripeLivemode() ? 'stripe_live_publishable_key' : 'stripe_test_publishable_key';
                break;
            case 'stripe_secret_key':
                $name = $this->isStripeLivemode() ? 'stripe_live_secret_key' : 'stripe_test_secret_key';
                break;
            case 'stripe_connect_client_id':
                $name = $this->isStripeLivemode() ? 'stripe_live_connect_client_id' : 'stripe_test_connect_client_id';
                break;
            case 'mercadopago_access_token':
                $name = 'mercadopago_test_access_token';
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
                case 'sms_enabled':
                case 'subject_to_vat':
                    $value = (bool) $value;
                    break;
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

    public function canSendSms()
    {
        if (!$this->get('sms_enabled')) {

            return false;
        }

        $smsGateway = $this->get('sms_gateway');

        if ('mailjet' !== $smsGateway) {

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

        $whitelist = ['be', 'es', 'de', 'fr'];

        if (!in_array($this->country, $whitelist)) {

            return false;
        }

        return isset($smsGatewayConfig['api_token']);
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

                if ($name === 'sms_enabled') {
                    $value = (bool) $value;
                }

                if ($name === 'subject_to_vat') {
                    $value = (bool) $value;
                }

                $settings->$name = $value;
            } catch (\RuntimeException $e) {}
        }

        return $settings;
    }
}
