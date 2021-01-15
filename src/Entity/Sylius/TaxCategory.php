<?php

namespace AppBundle\Entity\Sylius;

use Sylius\Component\Taxation\Model\TaxCategory as BaseTaxCategory;
use Doctrine\Common\Collections\ArrayCollection;

class TaxCategory extends BaseTaxCategory
{
    protected $countries;

    public function __construct()
    {
        parent::__construct();

        $this->countries = new ArrayCollection();
    }

    public function getCountries()
    {
        return $this->countries;
    }

    public function getRatesByCountry($country)
    {
        return $this->getRates()->filter(function ($rate) use ($country) {
            if (is_callable([ $rate, 'getCountry' ])) {
                return strtolower($rate->getCountry()) === strtolower($country);
            } else {
                return true;
            }
        });
    }
}
