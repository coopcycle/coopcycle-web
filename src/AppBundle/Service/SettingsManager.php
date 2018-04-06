<?php

namespace AppBundle\Service;

use Craue\ConfigBundle\Util\Config as CraueConfig;
use Doctrine\Common\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;

class SettingsManager
{
    private $craueConfig;
    private $configEntityName;
    private $doctrine;
    private $logger;

    private $settings = [
        'brand_name',
        'administrator_email',
        'stripe_publishable_key',
        'stripe_secret_key',
        'google_api_key',
        'latlng',
        'default_tax_category',
    ];

    private $secretSettings = [
        'stripe_publishable_key',
        'stripe_secret_key',
        'google_api_key',
    ];

    public function __construct(CraueConfig $craueConfig, $configEntityName, ManagerRegistry $doctrine, LoggerInterface $logger)
    {
        $this->craueConfig = $craueConfig;
        $this->configEntityName = $configEntityName;
        $this->doctrine = $doctrine;
        $this->logger = $logger;
    }

    public function getSettings()
    {
        return $this->settings;
    }

    public function isSecret($name)
    {
        return in_array($name, $this->secretSettings);
    }

    public function get($name)
    {
        try {
            return $this->craueConfig->get($name);
        } catch (\RuntimeException $e) {}
    }

    public function set($name, $value)
    {
        $className = $this->configEntityName;

        $setting = $this->doctrine
            ->getRepository($className)
            ->findOneBy([
                'name' => $name
            ]);

        if (!$setting) {

            $setting = new $className();
            $setting->setSection('general');
            $setting->setName($name);

            $this->doctrine
                ->getManagerForClass($className)
                ->persist($setting);
        }

        $setting->setValue($value);
    }

    public function flush()
    {
        $this->doctrine->getManagerForClass($this->configEntityName)->flush();
    }

    public function isFullyConfigured()
    {
        foreach ($this->settings as $name) {
            try {
                $this->craueConfig->get($name);
            } catch (\RuntimeException $e) {
                return false;
            }
        }

        return true;
    }
}
