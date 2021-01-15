<?php

namespace AppBundle\Entity\Sylius;

use Sylius\Component\Taxation\Model\TaxRate as BaseTaxRate;

class TaxRate extends BaseTaxRate
{
    /**
     * @var string
     */
    protected $country;

    protected $validFrom;
    protected $validTo;

    /**
     * @return string|null
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

    /**
     * @return mixed
     */
    public function getValidFrom()
    {
        return $this->validFrom;
    }

    /**
     * @param mixed $validFrom
     *
     * @return self
     */
    public function setValidFrom($validFrom)
    {
        $this->validFrom = $validFrom;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getValidTo()
    {
        return $this->validTo;
    }

    /**
     * @param mixed $validTo
     *
     * @return self
     */
    public function setValidTo($validTo)
    {
        $this->validTo = $validTo;

        return $this;
    }
}
