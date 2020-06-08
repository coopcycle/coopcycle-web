<?php

namespace AppBundle\Entity\Sylius;

use Sylius\Component\Taxation\Model\TaxRate as BaseTaxRate;

class TaxRate extends BaseTaxRate
{
    /**
     * @var string
     */
    protected $country;

    /**
     * @return string
     */
    public function getCountry()
    {
        return $this->country;
    }

    /**
     * @param string $country
     */
    public function setCountry(string $country): void
    {
        $this->country = $country;
    }
}
