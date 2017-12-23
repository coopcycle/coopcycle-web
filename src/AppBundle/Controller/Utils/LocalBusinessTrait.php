<?php

namespace AppBundle\Controller\Utils;

trait LocalBusinessTrait
{
    private function getLocalizedLocalBusinessProperties()
    {
        $countryCode = $this->getParameter('country_iso');
        $additionalProperties = [];

        switch ($countryCode) {
            case 'fr':
                $additionalProperties[] = 'siret';
            default:
                break;
        }

        return $additionalProperties;
    }
}
