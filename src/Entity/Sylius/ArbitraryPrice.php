<?php

namespace AppBundle\Entity\Sylius;

use Symfony\Component\Serializer\Annotation\Groups;


class ArbitraryPrice implements PriceInterface
{
    /**
     * @var int
     */
    #[Groups(['delivery_create'])]
    private $variantPrice;

    /**
     * @var ?string
     */
    #[Groups(['delivery_create'])]
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
