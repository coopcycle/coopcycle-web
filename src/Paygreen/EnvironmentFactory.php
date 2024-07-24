<?php

namespace AppBundle\Paygreen;

use AppBundle\Service\SettingsManager;
use Paygreen\Sdk\Payment\V3\Environment;

class EnvironmentFactory
{
    public function __construct(private SettingsManager $settingsManager, private string $environment)
    {}

    public function __invoke(): Environment
    {
        return new Environment(
            $this->settingsManager->get('paygreen_shop_id'),
            $this->settingsManager->get('paygreen_secret_key'),
            $this->environment
        );
    }
}

