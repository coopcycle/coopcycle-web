<?php

namespace AppBundle\Entity\Sylius;

class ArbitraryPrice implements PriceInterface
{
    /**
     * @var int
     */
    private $variantPrice;

    /**
     * @var ?string
     */
    private $variantName;

    public function __construct(
        ?string $variantName,
        int $variantPrice,
    ) {
        $this->variantName = $variantName;
        $this->variantPrice = $variantPrice;
    }

    public function getVariantName(): ?string
    {
        return $this->variantName;
    }

    public function getValue(): ?int
    {
        return $this->variantPrice;
    }

}
