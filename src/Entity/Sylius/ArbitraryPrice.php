<?php

namespace AppBundle\Entity\Sylius;

use Symfony\Component\Serializer\Annotation\Groups;


class ArbitraryPrice implements PriceInterface
{
    public function __construct(
        private readonly ?string $variantName,
        private readonly int $variantPrice,
    ) {
    }

    #[Groups(['delivery', 'delivery_create'])]
    public function getVariantName(): ?string
    {
        return $this->variantName;
    }

    #[Groups(['delivery', 'delivery_create'])]
    public function getValue(): ?int
    {
        return $this->variantPrice;
    }

}
