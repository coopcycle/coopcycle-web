<?php

declare(strict_types=1);

namespace AppBundle\Sylius\Currency;

use AppBundle\Service\SettingsManager;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Sylius\Component\Currency\Context\CurrencyNotFoundException;

final class SettingsAwareCurrencyContext implements CurrencyContextInterface
{
    private $settingsManager;

    public function __construct(SettingsManager $settingsManager)
    {
        $this->settingsManager = $settingsManager;
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrencyCode(): string
    {
        try {
            $currencyCode = $this->settingsManager->get('currency_code');
        } catch (\RuntimeException $e) {}

        if (!empty($currencyCode)) {
            return $currencyCode;
        }

        return 'EUR';
    }
}
