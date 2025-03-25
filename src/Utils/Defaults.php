<?php

namespace AppBundle\Utils;

use AppBundle\Service\Geocoder;
use AppBundle\Service\SettingsManager;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class Defaults
{
    public function __construct(
        private SettingsManager $settingsManager,
        private Geocoder $geocoder,
        private CacheInterface $appCache)
    {}

    public function getAddressLocality(): string
    {
        return $this->appCache->get('defaults.city', function (ItemInterface $item) {

            $item->expiresAfter(60 * 60);

            [ $latitude, $longitude ] = explode(',', $this->settingsManager->get('latlng'));

            $address = $this->geocoder->reverse($latitude, $longitude);

            return $address->getAddressLocality();
        });
    }

    public function getPostalCode(): string
    {
        return $this->appCache->get('defaults.postal_code', function (ItemInterface $item) {

            $item->expiresAfter(60 * 60);

            [ $latitude, $longitude ] = explode(',', $this->settingsManager->get('latlng'));

            $address = $this->geocoder->reverse($latitude, $longitude);

            return $address->getPostalCode();
        });
    }
}
