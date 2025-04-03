<?php

namespace AppBundle\Entity\Sylius;

use Symfony\Component\Serializer\Annotation\Groups;

interface PriceInterface
{
    #[Groups(['order'])]
    public function getVariantName(): ?string;

    #[Groups(['order'])]
    public function getValue(): ?int;
}
