<?php

namespace AppBundle\Enum;

use MyCLabs\Enum\Enum;

/**
 * Optins availables on platform to ask and store user consents
 *
 */
class Optin extends Enum
{
    /**
     * If you want to add a new Optin, just declare a new constant for it
     */
    const NEWSLETTER = 'newsletter';
    const MARKETING = 'marketing';

    /**
     * Customization of Optin labels
     */
    public function label(): string
    {
        switch($this)
        {
            case Optin::NEWSLETTER:
                return 'form.registration.optin.newsletter.label';
            case Optin::MARKETING:
                return 'form.registration.optin.marketing.label';
            default:
                return '';
        }
    }

    /**
     * Parameters for customized Optin labels
     */
    public function labelParameters($settingsManager): array
    {
        switch($this)
        {
            case Optin::NEWSLETTER :
                return [
                    '%brand_name%' => $settingsManager->get('brand_name')
                ];
            case Optin::MARKETING:
                return [];
            default:
                return [];
        };
    }

    /**
     * Specify if the Optint is required, false by default
     */
    public function required(): bool
    {
        switch($this)
        {
            case Optin::NEWSLETTER:
            case Optin::MARKETING:
                return false;
            default:
                return false;
        };
    }

    /**
     * Customization of Optin export label
     */
    public function exportLabel(): string
    {
        switch($this)
        {
            case Optin::NEWSLETTER:
                return 'adminDashboard.users.export.optins.newsletter';
            case Optin::MARKETING:
                return 'adminDashboard.users.export.optins.marketting';
            default:
                return '';
        }
    }
}
