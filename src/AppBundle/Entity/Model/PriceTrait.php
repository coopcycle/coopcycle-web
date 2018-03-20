<?php

namespace AppBundle\Entity\Model;

use ApiPlatform\Core\Annotation\ApiProperty;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

trait PriceTrait
{
    /**
     * @var float The offer price of a product, or of a price component when attached to PriceSpecification and its subtypes.
     *
     * Usage guidelines:
     *  - Use the [priceCurrency](/priceCurrency) property (with [ISO 4217 codes](http://en.wikipedia.org/wiki/ISO_4217#Active_codes) e.g. "USD") instead of including [ambiguous symbols](http://en.wikipedia.org/wiki/Dollar_sign#Currencies_that_use_the_dollar_or_peso_sign) such as '$' in the value.
     *  - Use '.' (Unicode 'FULL STOP' (U+002E)) rather than ',' to indicate a decimal point. Avoid using these symbols as a readability separator.
     *  - Note that both [RDFa](http://www.w3.org/TR/xhtml-rdfa-primer/#using-the-content-attribute) and Microdata syntax allow the use of a "content=" attribute for publishing simple machine-readable values alongside more human-friendly formatting.
     *  - Use values from 0123456789 (Unicode 'DIGIT ZERO' (U+0030) to 'DIGIT NINE' (U+0039)) rather than superficially similiar Unicode symbols.
     *
     * @ApiProperty(iri="https://schema.org/price")
     * @Groups({"order"})
     */
    protected $price;

    /**
     * Sets price.
     *
     * @param float $price
     *
     * @return $this
     */
    public function setPrice($price)
    {
        $this->price = $price;

        return $this;
    }

    /**
     * Gets price.
     *
     * @return float
     */
    public function getPrice()
    {
        return $this->price;
    }
}
