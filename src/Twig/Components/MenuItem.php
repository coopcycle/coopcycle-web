<?php

namespace AppBundle\Twig\Components;

use AppBundle\Entity\Sylius\Product;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class MenuItem
{
    use ComponentToolsTrait;
    use DefaultActionTrait;

    #[LiveProp]
    public Product $product;
}
