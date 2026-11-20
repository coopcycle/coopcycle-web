<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use AppBundle\Api\Resource\ShopCuisines;
use AppBundle\Service\ShopFilterRegistry;

final class ShopCuisinesProvider implements ProviderInterface
{
    public function __construct(private ShopFilterRegistry $shopFilterRegistry)
    {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ShopCuisines
    {
        return new ShopCuisines($this->shopFilterRegistry->getCuisines());
    }
}
