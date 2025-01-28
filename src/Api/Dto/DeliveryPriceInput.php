<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

class DeliveryPriceInput
{
    /**
     * @var int
     * @Groups({"delivery_create"})
     */
    private $priceIncVATcents;

    /**
     * @var string
     * @Groups({"delivery_create"})
     */
    private $variantName;

    /**
     * Get the value of priceIncVATcents
     */
    public function getPriceIncVATcents(): int
    {
        return $this->priceIncVATcents;
    }

    /**
     * Set the value of priceIncVATcents
     */
    public function setPriceIncVATcents(int $priceIncVATcents): self
    {
        $this->priceIncVATcents = $priceIncVATcents;

        return $this;
    }

    /**
     * Get the value of variantName
     */
    public function getVariantName(): string
    {
        return $this->variantName;
    }

    /**
     * Set the value of variantName
     */
    public function setVariantName(string $variantName): self
    {
        $this->variantName = $variantName;

        return $this;
    }
}
