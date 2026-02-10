<?php

namespace AppBundle\Twig\Components;

use AppBundle\Service\TimingRegistry;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class FulfillmentBadge
{
    use ComponentToolsTrait;
    use DefaultActionTrait;

    #[LiveProp]
    public bool $isPreOrder = false;

    #[LiveProp]
    public int $id;

    public function __construct(private TimingRegistry $timingRegistry)
    {
    }

    public function getFulfillmentMethods(): array
    {
        return $this->timingRegistry->getAllFulfilmentMethodsForId($this->id);
    }

}

